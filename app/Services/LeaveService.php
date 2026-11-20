<?php

namespace App\Services;

use App\Models\ElLedger;
use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Leave accounting rules taken from "El Calculation 2025.xlsx":
 *  - Every employee gets CL 6 + SL 6 + EL 16 = 28 days per calendar year (pro-rated in the joining / exit year).
 *  - Leave taken beyond the 28-day pool in a year is Loss of Pay (LOP).
 *  - Unused leave at year end is credited as EL, capped at 16 days: credit = min(16, 28 - leave used).
 *  - EL credits accumulate year on year and are encashed at final settlement (days x Basic/26).
 */
class LeaveService
{
    /** @var array<string, Collection> */
    private array $typeCache = [];

    public function types(): Collection
    {
        return $this->typeCache['all'] ??= LeaveType::ordered()->get();
    }

    public function lopType(): ?LeaveType
    {
        return $this->types()->firstWhere('is_paid', false);
    }

    /** Count leave days (excluding the weekly off) between two dates. */
    public function countDays(Carbon $from, Carbon $to, bool $halfDay = false): float
    {
        if ($halfDay) {
            return 0.5;
        }
        $off = (int) Setting::get('weekly_off', 0);
        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->dayOfWeek !== $off) {
                $days++;
            }
        }

        return (float) $days;
    }

    /** Split a leave request into [ 'Y-m' => days ] across the months it touches. */
    public function splitByMonth(LeaveRequest $r): array
    {
        $from = $r->from_date->copy();
        $to = $r->to_date->copy();
        if ($r->half_day || $from->format('Y-m') === $to->format('Y-m')) {
            return [$from->format('Y-m') => (float) $r->days];
        }
        $off = (int) Setting::get('weekly_off', 0);
        $parts = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->dayOfWeek !== $off) {
                $parts[$d->format('Y-m')] = ($parts[$d->format('Y-m')] ?? 0) + 1;
            }
        }
        // keep the admin-entered total authoritative: adjust the last month for any difference
        $diff = (float) $r->days - array_sum($parts);
        if ($parts && $diff != 0) {
            $last = array_key_last($parts);
            $parts[$last] = max(0, $parts[$last] + $diff);
        }

        return $parts;
    }

    /**
     * Usage per month for one employee and year.
     * Returns [month => ['types' => [code => days], 'unspecified' => days, 'paid' => days, 'lop' => days, 'total' => days]].
     */
    public function monthlyUsage(Employee $emp, int $year): array
    {
        $types = $this->types()->keyBy('id');
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = ['types' => [], 'unspecified' => 0.0, 'paid' => 0.0, 'lop' => 0.0, 'total' => 0.0];
        }

        $requests = LeaveRequest::where('employee_id', $emp->id)->where('status', 'approved')
            ->whereDate('from_date', '<=', "$year-12-31")->whereDate('to_date', '>=', "$year-01-01")->get();
        foreach ($requests as $r) {
            $type = $types[$r->leave_type_id] ?? null;
            foreach ($this->splitByMonth($r) as $ym => $days) {
                [$y, $m] = array_map('intval', explode('-', $ym));
                if ($y !== $year) {
                    continue;
                }
                $this->addUsage($months[$m], $type, $days);
            }
        }

        $adjustments = LeaveAdjustment::where('employee_id', $emp->id)->where('year', $year)->get();
        foreach ($adjustments as $a) {
            $this->addUsage($months[$a->month], $a->leave_type_id ? ($types[$a->leave_type_id] ?? null) : null, (float) $a->days);
        }

        return $months;
    }

    private function addUsage(array &$bucket, ?LeaveType $type, float $days): void
    {
        $bucket['total'] += $days;
        if ($type && ! $type->is_paid) {
            $bucket['lop'] += $days;

            return;
        }
        $bucket['paid'] += $days;
        if ($type) {
            $bucket['types'][$type->code] = ($bucket['types'][$type->code] ?? 0) + $days;
        } else {
            $bucket['unspecified'] += $days;
        }
    }

    /** Fraction of the year the employee is entitled to (pro-rata for the joining and exit year). */
    public function entitlementFactor(Employee $emp, int $year): float
    {
        $start = 1;
        $end = 12;
        if ($emp->date_of_joining) {
            if ($emp->date_of_joining->year > $year) {
                return 0;
            }
            if ($emp->date_of_joining->year === $year) {
                $start = $emp->date_of_joining->month;
            }
        }
        if ($emp->date_of_exit) {
            if ($emp->date_of_exit->year < $year) {
                return 0;
            }
            if ($emp->date_of_exit->year === $year) {
                $end = $emp->date_of_exit->month;
            }
        }

        return max(0, $end - $start + 1) / 12;
    }

    public function pool(Employee $emp, int $year): float
    {
        return round(Setting::num('annual_leave_pool') * $this->entitlementFactor($emp, $year), 2);
    }

    /**
     * Balances for the year, considering usage up to and including $uptoMonth.
     * Usage without a specific type (imported from the register) is charged to SL, then CL, then EL.
     */
    public function balances(Employee $emp, int $year, int $uptoMonth = 12, ?array $usage = null): array
    {
        $usage ??= $this->monthlyUsage($emp, $year);
        $factor = $this->entitlementFactor($emp, $year);
        $rows = [];
        $unspecified = 0;
        $lop = 0;
        foreach ($usage as $m => $u) {
            if ($m > $uptoMonth) {
                break;
            }
            $unspecified += $u['unspecified'];
            $lop += $u['lop'];
            foreach ($u['types'] as $code => $d) {
                $rows[$code]['used'] = ($rows[$code]['used'] ?? 0) + $d;
            }
        }
        $paidTypes = $this->types()->where('is_paid', true);
        foreach ($paidTypes as $t) {
            $rows[$t->code] = [
                'code' => $t->code,
                'name' => $t->name,
                'quota' => round($t->yearly_quota * $factor, 2),
                'used' => round($rows[$t->code]['used'] ?? 0, 2),
            ];
        }
        foreach (['SL', 'CL', 'EL'] as $code) {
            if ($unspecified <= 0 || ! isset($rows[$code])) {
                continue;
            }
            $free = max(0, $rows[$code]['quota'] - $rows[$code]['used']);
            $take = min($free, $unspecified);
            $rows[$code]['used'] += $take;
            $unspecified -= $take;
        }
        $totalUsed = 0;
        foreach ($rows as $code => &$r) {
            $r['balance'] = round($r['quota'] - $r['used'], 2);
            $totalUsed += $r['used'];
        }
        unset($r);
        $totalUsed += $unspecified; // usage that could not be charged to any type (exceeds the pool)
        $pool = $this->pool($emp, $year);

        return [
            'year' => $year,
            'types' => array_values($rows),
            'pool' => $pool,
            'used' => round($totalUsed, 2),
            'balance' => round(max(0, $pool - $totalUsed), 2),
            'excess' => round(max(0, $totalUsed - $pool), 2),
            'lop' => round($lop, 2),
        ];
    }

    /**
     * Leave days to be treated as LOP in a given month: explicit LOP leave + paid leave that pushed the
     * year-to-date total beyond the annual pool.
     */
    public function lopForMonth(Employee $emp, int $year, int $month, ?array $usage = null): array
    {
        $usage ??= $this->monthlyUsage($emp, $year);
        $pool = $this->pool($emp, $year);
        $before = 0;
        for ($m = 1; $m < $month; $m++) {
            $before += $usage[$m]['paid'];
        }
        $through = $before + $usage[$month]['paid'];
        $excess = max(0, $through - $pool) - max(0, $before - $pool);

        return [
            'absent' => round($usage[$month]['total'], 2),
            'explicit_lop' => round($usage[$month]['lop'], 2),
            'excess_lop' => round($excess, 2),
            'lop' => round($usage[$month]['lop'] + $excess, 2),
        ];
    }

    /** EL credited for a year: manual ledger entry if present, otherwise computed from usage. */
    public function elCreditComputed(Employee $emp, int $year): float
    {
        $factor = $this->entitlementFactor($emp, $year);
        if ($factor <= 0) {
            return 0;
        }
        $usage = $this->monthlyUsage($emp, $year);
        $used = array_sum(array_column($usage, 'paid'));
        $elCap = Setting::num('el_max_per_year') * $factor;

        return round(min($elCap, max(0, $this->pool($emp, $year) - $used)), 2);
    }

    /** Year-wise EL history from joining year to exit year / current year. */
    public function elHistory(Employee $emp, ?int $untilYear = null): array
    {
        if (! $emp->date_of_joining) {
            return [];
        }
        $untilYear ??= $emp->date_of_exit?->year ?? (int) now()->year;
        $manual = ElLedger::where('employee_id', $emp->id)->get()->keyBy('year');
        $rows = [];
        for ($y = $emp->date_of_joining->year; $y <= $untilYear; $y++) {
            $isManual = isset($manual[$y]);
            $rows[] = [
                'year' => $y,
                'period' => $this->periodLabel($emp, $y),
                'days' => $isManual ? (float) $manual[$y]->days : $this->elCreditComputed($emp, $y),
                'source' => $isManual ? 'manual' : 'computed',
                'note' => $isManual ? $manual[$y]->note : null,
                'provisional' => ! $isManual && $y >= (int) now()->year,
            ];
        }

        return $rows;
    }

    public function periodLabel(Employee $emp, int $year): string
    {
        $start = ($emp->date_of_joining && $emp->date_of_joining->year === $year) ? $emp->date_of_joining->format('M') : 'Jan';
        $end = ($emp->date_of_exit && $emp->date_of_exit->year === $year) ? $emp->date_of_exit->format('M') : 'Dec';

        return "$start to $end";
    }
}
