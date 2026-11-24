<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AllowanceType;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\Setting;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Monthly payroll runs (salary register) and payslip generation. */
class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll)
    {
    }

    public function index(Request $request)
    {
        $year = (int) ($request->input('year') ?: active_year());
        $runs = PayrollRun::with(['creator', 'finalizer'])->withCount('payslips')
            ->withSum('payslips', 'gross_salary')->withSum('payslips', 'net_salary')->withSum('payslips', 'total_amount')
            ->where('year', $year)->orderByDesc('month')->paginate(24)->withQueryString();
        $totals = \App\Models\Payslip::whereHas('run', fn ($q) => $q->where('year', $year))
            ->selectRaw('count(*) n, sum(gross_salary) gross, sum(net_salary) net, sum(total_amount) total')->first();

        return view('payroll.index', ['runs' => $runs, 'year' => $year, 'totals' => $totals, 'workingDays' => Setting::num('working_days')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'working_days' => ['required', 'numeric', 'min:1', 'max:31'],
        ]);
        [$year, $month] = array_map('intval', explode('-', $data['period']));
        $request->merge(['year' => $year]);
        $request->validate(['year' => [Rule::unique('payroll_runs')->where('month', $month)]], ['year.unique' => 'A payroll run for this month already exists.']);

        $run = PayrollRun::create(['year' => $year, 'month' => $month, 'working_days' => $data['working_days'], 'created_by' => auth()->id()]);
        $pending = OtEntry::where('year', $year)->where('month', $month)->where('status', 'pending')->count();
        $n = $this->payroll->generate($run);
        ActivityLog::record('payroll', "Created payroll {$run->periodLabel()} ($n payslips)");

        return redirect()->route('payroll.show', $run)->with('success', "$n payslips generated for {$run->periodLabel()}.")
            ->with('warning', $pending ? "$pending OT entries for this month are still pending approval and were not included." : null);
    }

    public function show(Request $request, PayrollRun $run)
    {
        $payslips = $run->payslips()->with('employee:id,email')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('emp_code', 'like', "%$s%")))
            ->when($request->email === 'sent', fn ($q) => $q->whereNotNull('emailed_at'))
            ->when($request->email === 'unsent', fn ($q) => $q->whereNull('emailed_at'))
            ->when($request->email === 'failed', fn ($q) => $q->whereNull('emailed_at')->whereNotNull('email_error'))
            ->when($request->email === 'noemail', fn ($q) => $q->whereHas('employee', fn ($e) => $e->where(fn ($w) => $w->whereNull('email')->orWhere('email', ''))))
            ->orderBy('emp_code')->get();
        $pendingOt = OtEntry::where('year', $run->year)->where('month', $run->month)->where('status', 'pending')->count();

        return view('payroll.show', compact('run', 'payslips', 'pendingOt'));
    }

    public function generate(Request $request, PayrollRun $run)
    {
        abort_if($run->isFinalized(), 422, 'This payroll is finalized.');
        $request->validate(['working_days' => ['nullable', 'numeric', 'min:1', 'max:31']]);
        if ($request->filled('working_days')) {
            $run->update(['working_days' => $request->working_days]);
        }
        $reset = $request->boolean('reset');
        $n = $this->payroll->generate($run, $reset);
        ActivityLog::record('payroll', "Regenerated payroll {$run->periodLabel()}".($reset ? ' (reset manual entries)' : ''));

        return back()->with('success', "$n payslips recalculated from the latest employee, leave and OT data.");
    }

    public function finalize(PayrollRun $run)
    {
        abort_if($run->isFinalized(), 422, 'Already finalized.');
        $run->update(['status' => 'finalized', 'finalized_by' => auth()->id(), 'finalized_at' => now()]);
        ActivityLog::record('payroll', "Finalized payroll {$run->periodLabel()}");

        return back()->with('success', 'Payroll finalized. Payslips, leave and OT for this month are now locked.');
    }

    public function reopen(PayrollRun $run)
    {
        $run->update(['status' => 'draft', 'finalized_by' => null, 'finalized_at' => null]);
        ActivityLog::record('payroll', "Re-opened payroll {$run->periodLabel()}");

        return back()->with('success', 'Payroll re-opened for changes.');
    }

    public function destroy(PayrollRun $run)
    {
        abort_if($run->isFinalized(), 422, 'Re-open the payroll before deleting it.');
        $label = $run->periodLabel();
        $run->delete();
        ActivityLog::record('payroll', "Deleted payroll $label");

        return redirect()->route('payroll.index')->with('success', "Payroll $label deleted.");
    }

    /** Salary register in the column layout of the "Salary Workings" sheet. */
    /** Salary register in the layout of the monthly salary sheet (see App\Support\SalaryRegisterExport). */
    public function export(PayrollRun $run)
    {
        [$header, $rows] = \App\Support\SalaryRegisterExport::build($run);

        return xlsx_download('salary-register-'.$run->year.'-'.str_pad($run->month, 2, '0', STR_PAD_LEFT).'.xlsx', $header, $rows,
            ['title' => $run->periodLabel(), 'highlight' => ['/^TOTAL$/' => 'E0E7FF'],
                'number_format' => array_fill_keys(range(7, count($header) - 2), '#,##0.00')]);
    }

    public function email(Request $request, PayrollRun $run, \App\Services\PayslipMailer $mailer)
    {
        abort_unless($run->isFinalized(), 422, 'Finalize the payroll before emailing payslips.');
        @set_time_limit(0);
        $query = $run->payslips()->with('employee', 'run');
        if (! $request->boolean('resend')) {
            $query->whereNull('emailed_at');
        }
        if ($request->filled('ids')) {
            $query->whereIn('id', (array) $request->ids);
        }
        $sent = $failed = 0;
        foreach ($query->get() as $p) {
            $mailer->send($p) ? $sent++ : $failed++;
            if ($mailer->fatal) {
                // wrong SMTP login / server unreachable: every further attempt would fail the same way
                ActivityLog::record('email', "Emailing payslips for {$run->periodLabel()} stopped: {$p->email_error}");

                return back()->with('error', "Emailing stopped after $sent sent. {$p->email_error}");
            }
        }
        ActivityLog::record('email', "Emailed payslips for {$run->periodLabel()}: $sent sent, $failed failed");

        return back()->with('success', "$sent payslip(s) emailed.")
            ->with($failed ? 'error' : 'info', $failed ? "$failed could not be sent — see the Email column for the reason." : null);
    }

    /** All payslips of the month in one PDF. */
    public function pdf(PayrollRun $run)
    {
        $payslips = $run->payslips()->orderBy('emp_code')->get();
        ActivityLog::record('payroll', "Downloaded all payslips PDF for {$run->periodLabel()}");

        return Pdf::loadView('payslips.pdf', ['payslips' => $payslips, 'run' => $run, 's' => Setting::allValues()])
            ->setPaper('a4')->download('payslips-'.$run->year.'-'.str_pad($run->month, 2, '0', STR_PAD_LEFT).'.pdf');
    }
}
