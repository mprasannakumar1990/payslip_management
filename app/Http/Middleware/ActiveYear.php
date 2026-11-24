<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Services\LeaveService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The year every page works with (chosen in the top bar). A page opened with ?year=YYYY or ?period=YYYY-MM switches
 * the whole app to that year, so all pages stay in step.
 *
 * Also makes sure the yearly leave allocation exists for the current year: the first signed-in request of a new
 * year generates CL / SL / EL for every employee on the rolls, so nothing has to be run by hand on 1 January.
 */
class ActiveYear
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->isMethod('GET') && ! $request->prefetch()) {
            $year = null;
            if (preg_match('/^\d{4}$/', (string) $request->query('year'))) {
                $year = (int) $request->query('year');
            } elseif (preg_match('/^(\d{4})-\d{2}$/', (string) $request->query('period'), $m)
                || preg_match('/^(\d{4})-\d{1,2}$/', (string) $request->query('month'), $m)) {
                $year = (int) $m[1];
            }
            if ($year && $year >= 2000 && $year <= (int) now()->year + 1) {
                session(['active_year' => $year]);
            }
            $this->ensureLeaveGenerated();
        }

        return $next($request);
    }

    private function ensureLeaveGenerated(): void
    {
        $year = (int) now()->year;
        if (Cache::get('leave-generated-'.$year)) {
            return;
        }
        try {
            $leave = app(LeaveService::class);
            if (! $leave->isGenerated($year)) {
                $s = $leave->generateYear($year);
                ActivityLog::record('leave', "Yearly leave generated automatically for $year ({$s['employees']} employees)");
            }
            Cache::forever('leave-generated-'.$year, 1);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
