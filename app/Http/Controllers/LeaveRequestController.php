<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveService $leave)
    {
    }

    public function index(Request $request)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $month = (int) $request->input('month'); // 1-12 within the selected year
        $start = Carbon::create($year, $month ?: 1, 1);
        $end = $month ? $start->copy()->endOfMonth() : Carbon::create($year, 12, 31);
        $inPeriod = fn ($q) => $q->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start);

        $requests = LeaveRequest::with(['employee', 'leaveType', 'creator', 'actioner'])
            ->where($inPeriod)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->employee_id, fn ($q, $e) => $q->where('employee_id', $e))
            ->when($request->leave_type_id, fn ($q, $t) => $q->where('leave_type_id', $t))
            ->orderByRaw("status = 'pending' desc")->latest('from_date')->paginate(25)->withQueryString();

        return view('leave.requests.index', [
            'requests' => $requests,
            'year' => $year,
            'month' => $month,
            'employees' => Employee::onRollsIn($year)->orderBy('name')->get(['id', 'emp_code', 'name']),
            'types' => LeaveType::ordered()->get(),
            'counts' => LeaveRequest::where($inPeriod)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        return view('leave.requests.form', [
            'leave' => new LeaveRequest(['employee_id' => $request->employee_id, 'from_date' => now(), 'to_date' => now(), 'status' => 'approved']),
            'employees' => Employee::active()->orderBy('name')->get(['id', 'emp_code', 'name']),
            'types' => LeaveType::ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['status'] = $request->boolean('approve_now') ? 'approved' : 'pending';
        if ($data['status'] === 'approved') {
            $data['actioned_by'] = auth()->id();
            $data['actioned_at'] = now();
        }
        $leave = LeaveRequest::create($data + ['created_by' => auth()->id()]);
        ActivityLog::record('leave', "Added {$leave->leaveType->code} leave ({$leave->days} d) for {$leave->employee->label()} [{$leave->status}]");

        return redirect()->route('leave-requests.index')->with('success', 'Leave request saved.')->with('warning', $this->balanceWarning($leave));
    }

    public function edit(LeaveRequest $leaveRequest)
    {
        $this->ensureEditable($leaveRequest);

        return view('leave.requests.form', [
            'leave' => $leaveRequest,
            'employees' => Employee::orderBy('name')->get(['id', 'emp_code', 'name']),
            'types' => LeaveType::ordered()->get(),
        ]);
    }

    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        $this->ensureEditable($leaveRequest);
        $leaveRequest->update($this->validated($request, $leaveRequest));
        ActivityLog::record('leave', "Updated leave #{$leaveRequest->id} for {$leaveRequest->employee->label()}");

        return redirect()->route('leave-requests.index')->with('success', 'Leave request updated.')->with('warning', $this->balanceWarning($leaveRequest));
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        $this->ensureEditable($leaveRequest);
        $leaveRequest->delete();
        ActivityLog::record('leave', "Deleted leave #{$leaveRequest->id} for {$leaveRequest->employee->label()}");

        return back()->with('success', 'Leave request deleted.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        return $this->action($request, $leaveRequest, 'approved');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        return $this->action($request, $leaveRequest, 'rejected');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        return $this->action($request, $leaveRequest, 'cancelled');
    }

    private function action(Request $request, LeaveRequest $leave, string $status)
    {
        $this->ensureEditable($leave);
        $request->validate(['admin_remarks' => ['nullable', 'string', 'max:500']]);
        $leave->update([
            'status' => $status,
            'admin_remarks' => $request->admin_remarks ?? $leave->admin_remarks,
            'actioned_by' => auth()->id(),
            'actioned_at' => now(),
        ]);
        ActivityLog::record('leave', ucfirst($status)." leave #{$leave->id} for {$leave->employee->label()}");

        return back()->with('success', "Leave request {$status}.")
            ->with('warning', $status === 'approved' ? $this->balanceWarning($leave) : null);
    }

    /** AJAX: number of leave days between two dates (weekly off excluded). */
    public function calcDays(Request $request)
    {
        $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        return response()->json(['days' => $this->leave->countDays(Carbon::parse($request->from), Carbon::parse($request->to), $request->boolean('half_day'))]);
    }

    private function validated(Request $request, ?LeaveRequest $current = null): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'days' => ['nullable', 'numeric', 'min:0.5', 'max:366'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'admin_remarks' => ['nullable', 'string', 'max:500'],
        ]);
        $data['half_day'] = $request->boolean('half_day');
        $from = Carbon::parse($data['from_date']);
        $to = Carbon::parse($data['to_date']);
        if ($data['half_day']) {
            $to = $from->copy();
            $data['to_date'] = $from->toDateString();
        }
        $data['days'] = $data['days'] ?? $this->leave->countDays($from, $to, $data['half_day']);
        if ($data['days'] <= 0) {
            throw ValidationException::withMessages(['to_date' => 'The selected dates contain no working days.']);
        }

        for ($d = $from->copy()->startOfMonth(); $d->lte($to); $d->addMonth()) {
            if (PayrollRun::isLocked($d->year, $d->month)) {
                throw ValidationException::withMessages(['from_date' => 'Payroll for '.$d->format('F Y').' is finalized; leave in that month cannot be changed.']);
            }
        }

        $overlap = LeaveRequest::where('employee_id', $data['employee_id'])->whereIn('status', ['pending', 'approved'])
            ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['from_date' => 'This employee already has a pending/approved leave overlapping these dates.']);
        }

        return $data;
    }

    private function ensureEditable(LeaveRequest $leave): void
    {
        for ($d = $leave->from_date->copy()->startOfMonth(); $d->lte($leave->to_date); $d->addMonth()) {
            abort_if(PayrollRun::isLocked($d->year, $d->month), 422, 'Payroll for '.$d->format('F Y').' is finalized; this leave can no longer be changed.');
        }
    }

    private function balanceWarning(LeaveRequest $leave): ?string
    {
        $leave->loadMissing('leaveType', 'employee');
        if (! $leave->leaveType->is_paid) {
            return null;
        }
        $b = $this->leave->balances($leave->employee, $leave->from_date->year);
        $type = collect($b['types'])->firstWhere('code', $leave->leaveType->code);
        if ($b['excess'] > 0) {
            return "{$leave->employee->name} has used {$b['used']} of {$b['pool']} leave days in {$b['year']}; {$b['excess']} day(s) beyond the yearly entitlement will be treated as Loss of Pay in payroll.";
        }
        if ($type && $type['balance'] < 0) {
            return "{$leave->leaveType->name} balance for {$leave->employee->name} is now {$type['balance']} day(s).";
        }

        return null;
    }
}
