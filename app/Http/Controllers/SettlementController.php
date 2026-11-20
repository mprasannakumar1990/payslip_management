<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Settlement;
use App\Services\LeaveService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Earn Leave final settlement + Gratuity, as in the per-employee sheets of "El Calculation 2025.xlsx":
 *   EL amount  = sum(year EL days x per-day rate)
 *   Gratuity   = Basic / 26 x 15 x years of service
 */
class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $items = Settlement::with(['employee', 'creator'])
            ->whereYear('settlement_date', $year)
            ->when($request->employee_id, fn ($q, $e) => $q->where('employee_id', $e))
            ->latest('settlement_date')->paginate(20)->withQueryString();

        return view('settlements.index', [
            'items' => $items,
            'year' => $year,
            'yearTotal' => Settlement::whereYear('settlement_date', $year)->sum('total_amount'),
            'employees' => Employee::orderBy('name')->get(['id', 'emp_code', 'name']),
        ]);
    }

    public function create(Request $request, LeaveService $leave)
    {
        $employee = $request->employee_id ? Employee::findOrFail($request->employee_id) : null;
        $prefill = null;
        if ($employee) {
            $exit = $request->date_of_exit ? Carbon::parse($request->date_of_exit) : ($employee->date_of_exit ?? now()->startOfDay());
            $clone = clone $employee;
            $clone->date_of_exit = $exit;
            $divisor = Setting::num('gratuity_divisor') ?: 26;
            $years = $employee->serviceYears($exit);
            $prefill = [
                'date_of_exit' => $exit->toDateString(),
                'per_day' => round($employee->basic_salary / $divisor),
                'rows' => $leave->elHistory($clone, $exit->year),
                'years' => $years,
                'gratuity' => round($employee->basic_salary / $divisor * Setting::num('gratuity_days') * $years),
                'eligible' => $years >= Setting::num('gratuity_min_years'),
            ];
        }

        return view('settlements.create', [
            'employees' => Employee::orderBy('name')->get(['id', 'emp_code', 'name', 'status']),
            'employee' => $employee,
            'prefill' => $prefill,
            's' => Setting::allValues(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date_of_exit' => ['nullable', 'date'],
            'settlement_date' => ['required', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'per_day_rate' => ['required', 'numeric', 'min:0'],
            'rows' => ['array'],
            'rows.*.year' => ['required', 'integer'],
            'rows.*.period' => ['nullable', 'string', 'max:40'],
            'rows.*.days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'gratuity_years' => ['required', 'numeric', 'min:0', 'max:60'],
            'gratuity_amount' => ['nullable', 'numeric', 'min:0'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'other_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $rows = collect($data['rows'] ?? [])->map(fn ($r) => [
            'year' => (int) $r['year'],
            'period' => $r['period'] ?? '',
            'days' => round((float) ($r['days'] ?? 0), 2),
            'amount' => round((float) ($r['days'] ?? 0) * $data['per_day_rate'], 2),
        ])->values();
        $elAmount = round($rows->sum('amount'), 2);
        $gratuity = isset($data['gratuity_amount']) ? (float) $data['gratuity_amount']
            : round($data['basic_salary'] / (Setting::num('gratuity_divisor') ?: 26) * Setting::num('gratuity_days') * $data['gratuity_years']);
        $sub = $elAmount + $gratuity + (float) ($data['bonus_amount'] ?? 0) + (float) ($data['other_amount'] ?? 0) - (float) ($data['deduction_amount'] ?? 0);
        $total = round($sub);

        $settlement = Settlement::create([
            'employee_id' => $data['employee_id'],
            'date_of_exit' => $data['date_of_exit'] ?? null,
            'settlement_date' => $data['settlement_date'],
            'basic_salary' => $data['basic_salary'],
            'per_day_rate' => $data['per_day_rate'],
            'el_rows' => $rows->all(),
            'el_days' => $rows->sum('days'),
            'el_amount' => $elAmount,
            'gratuity_years' => $data['gratuity_years'],
            'gratuity_amount' => $gratuity,
            'bonus_amount' => $data['bonus_amount'] ?? 0,
            'other_amount' => $data['other_amount'] ?? 0,
            'deduction_amount' => $data['deduction_amount'] ?? 0,
            'round_off' => round($total - $sub, 2),
            'total_amount' => $total,
            'remarks' => $data['remarks'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if ($request->boolean('mark_exited') && $data['date_of_exit']) {
            $settlement->employee->update(['status' => 'exited', 'date_of_exit' => $data['date_of_exit']]);
        }
        ActivityLog::record('settlement', "Created settlement for {$settlement->employee->label()} (Rs ".inr($total).')');

        return redirect()->route('settlements.show', $settlement)->with('success', 'Settlement saved.');
    }

    public function show(Settlement $settlement)
    {
        return view('settlements.show', ['st' => $settlement->load('employee', 'creator'), 's' => Setting::allValues()]);
    }

    public function pdf(Settlement $settlement)
    {
        $settlement->load('employee');

        return Pdf::loadView('settlements.pdf', ['st' => $settlement, 's' => Setting::allValues()])->setPaper('a4')
            ->download('settlement-'.Str::slug($settlement->employee->emp_code.'-'.$settlement->employee->name).'.pdf');
    }

    public function destroy(Settlement $settlement)
    {
        $settlement->delete();
        ActivityLog::record('settlement', "Deleted settlement #{$settlement->id} of {$settlement->employee->label()}");

        return redirect()->route('settlements.index')->with('success', 'Settlement deleted.');
    }
}
