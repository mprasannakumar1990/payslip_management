<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Payslip;
use App\Models\Setting;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayslipController extends Controller
{
    public function show(Payslip $payslip)
    {
        $payslip->load('run');

        return view('payslips.show', ['p' => $payslip, 's' => Setting::allValues()]);
    }

    public function edit(Payslip $payslip)
    {
        abort_if($payslip->run->isFinalized(), 422, 'This payroll is finalized. Re-open it to edit payslips.');

        return view('payslips.edit', ['p' => $payslip]);
    }

    public function update(Request $request, Payslip $payslip, PayrollService $service)
    {
        abort_if($payslip->run->isFinalized(), 422, 'This payroll is finalized.');
        $n = ['required', 'numeric', 'min:0', 'max:99999999'];
        $data = $request->validate([
            'present_days' => ['required', 'numeric', 'min:0', 'max:31'],
            'absent_days' => ['required', 'numeric', 'min:0', 'max:31'],
            'lop_days' => ['required', 'numeric', 'min:0', 'max:31'],
            'basic_salary' => $n,
            'second_shift_hours' => $n,
            'third_shift_hours' => $n,
            'ot_hours' => $n,
            'ot_multiplier' => ['required', 'numeric', 'min:0', 'max:5'],
            'ot_amount_manual' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'spl_ot_amount' => $n,
            'manager_special' => $n,
            'factory_special' => $n,
            'other_allowance' => $n,
            'general_allowance' => $n,
            'tax' => $n,
            'advance' => $n,
            'food_coupon' => $n,
            'other_deduction' => $n,
            'attendance_bonus' => $n,
            'birthday_welfare' => $n,
            'allowances' => ['array'],
            'allowances.*.name' => ['required', 'string', 'max:60'],
            'allowances.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'remarks' => ['nullable', 'string', 'max:250'],
        ]);
        $data['allowances'] = collect($data['allowances'] ?? [])
            ->filter(fn ($a) => (float) ($a['amount'] ?? 0) > 0)
            ->map(fn ($a) => ['name' => $a['name'], 'amount' => (float) $a['amount']])->values()->all();
        $data['esi_applicable'] = $request->boolean('esi_applicable');
        $data['pf_applicable'] = $request->boolean('pf_applicable');
        $service->recalculate($payslip, $data);
        ActivityLog::record('payroll', "Edited payslip {$payslip->emp_code} for {$payslip->run->periodLabel()}");

        return redirect()->route('payroll.show', $payslip->payroll_run_id)->with('success', "Payslip of {$payslip->name} updated.");
    }

    public function email(Payslip $payslip, \App\Services\PayslipMailer $mailer)
    {
        abort_unless($payslip->run->isFinalized(), 422, 'Finalize the payroll before emailing payslips.');
        $ok = $mailer->send($payslip);
        ActivityLog::record('email', ($ok ? 'Emailed' : 'Failed to email')." payslip {$payslip->emp_code} {$payslip->run->periodLabel()}");

        return back()->with($ok ? 'success' : 'error', $ok
            ? "Payslip emailed to {$payslip->emailed_to}."
            : "Could not email {$payslip->name}: {$payslip->email_error}");
    }

    public function pdf(Payslip $payslip)
    {
        $payslip->load('run');

        return Pdf::loadView('payslips.pdf', ['payslips' => collect([$payslip]), 'run' => $payslip->run, 's' => Setting::allValues()])
            ->setPaper('a4')
            ->download('payslip-'.Str::slug($payslip->emp_code.'-'.$payslip->name).'-'.$payslip->run->year.'-'.str_pad($payslip->run->month, 2, '0', STR_PAD_LEFT).'.pdf');
    }
}
