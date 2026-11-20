<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AllowanceType;
use App\Models\LeaveType;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('settings.edit', [
            's' => Setting::allValues(),
            'allowanceTypes' => AllowanceType::ordered()->get(),
            'leaveTypes' => LeaveType::ordered()->get(),
        ]);
    }

    public function update(Request $request)
    {
        $num = ['required', 'numeric', 'min:0'];
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'working_days' => ['required', 'numeric', 'min:1', 'max:31'],
            'second_shift_rate' => $num,
            'third_shift_rate' => $num,
            'ot_multiplier' => $num,
            'ot_hours_per_day' => ['required', 'numeric', 'min:1', 'max:24'],
            'pf_percent' => $num,
            'pf_cap' => $num,
            'esi_percent' => $num,
            'annual_leave_pool' => $num,
            'el_max_per_year' => $num,
            'gratuity_days' => $num,
            'gratuity_divisor' => ['required', 'numeric', 'min:1'],
            'gratuity_min_years' => $num,
            'krw_rate' => $num,
            'weekly_off' => ['required', 'integer', 'between:0,6'],
            'payslip_footer' => ['nullable', 'string', 'max:255'],
            'ot_groups' => ['required', 'string', 'max:1000'],
            'payroll_deadline_day' => ['required', 'integer', 'between:1,28'],
        ]);
        Setting::put($data);
        ActivityLog::record('settings', 'Updated application settings');

        return back()->with('success', 'Settings saved.');
    }

    public function updateMail(Request $request)
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:150'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:150'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:150'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],
            'mail_subject' => ['required', 'string', 'max:200'],
            'mail_body' => ['required', 'string', 'max:3000'],
        ]);
        // Gmail / Outlook / Microsoft 365 log in with the full email address, never a display name
        if (preg_match('/gmail|googlemail|office365|outlook|hotmail|live\.com/i', (string) $data['mail_host'])
            && $data['mail_username'] && ! filter_var($data['mail_username'], FILTER_VALIDATE_EMAIL)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mail_username' => "The SMTP username must be your full email address (e.g. name@gmail.com), not a name like \"{$data['mail_username']}\".",
            ]);
        }
        if ($request->filled('mail_password')) {
            // Gmail app passwords are shown as "abcd efgh ijkl mnop"; the spaces are not part of the password
            if (stripos((string) $data['mail_host'], 'gmail') !== false) {
                $data['mail_password'] = str_replace(' ', '', $data['mail_password']);
            }
            $data['mail_password'] = \Illuminate\Support\Facades\Crypt::encryptString($data['mail_password']);
        } else {
            unset($data['mail_password']); // keep the saved password
        }
        if ($request->boolean('clear_password')) {
            $data['mail_password'] = '';
        }
        $data = array_map(fn ($v) => $v ?? '', $data);
        Setting::put($data);
        ActivityLog::record('settings', 'Updated email (SMTP) settings');

        return redirect()->route('settings.edit', ['tab' => 'email'])->with('success', 'Email settings saved.');
    }

    public function testMail(Request $request)
    {
        $to = $request->validate(['test_to' => ['required', 'email', 'max:150']], ['test_to.email' => 'Enter a valid email address to send the test to.'])['test_to'];
        Setting::put(['mail_test_to' => $to]); // remembered for next time
        Setting::applyMailConfig();
        try {
            \Illuminate\Support\Facades\Mail::raw('Test email from '.Setting::get('company_name').' payslip system. Email settings are working.',
                fn ($m) => $m->to($to)->subject('Payslip system - test email'));
        } catch (\Throwable $e) {
            return redirect()->route('settings.edit', ['tab' => 'email'])->with('error', "Test email to $to failed: ".\App\Services\PayslipMailer::explain($e->getMessage())[0]);
        }
        $where = Setting::mailConfigured() ? $to : 'the log file (storage/logs) — SMTP is not configured yet';

        return redirect()->route('settings.edit', ['tab' => 'email'])->with('success', "Test email sent to $where.");
    }

    public function storeAllowanceType(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', 'unique:allowance_types,name']]);
        AllowanceType::create($data + ['sort_order' => (int) AllowanceType::max('sort_order') + 1, 'is_active' => true]);
        ActivityLog::record('settings', "Added allowance type {$data['name']}");

        return back()->with('success', 'Allowance type added.');
    }

    public function updateAllowanceType(Request $request, AllowanceType $type)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:allowance_types,name,'.$type->id],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $type->update($data);
        ActivityLog::record('settings', "Updated allowance type {$type->name}");

        return back()->with('success', 'Allowance type updated.');
    }

    public function updateLeaveType(Request $request, LeaveType $type)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'yearly_quota' => ['required', 'numeric', 'min:0', 'max:365'],
        ]);
        $type->update($data);
        ActivityLog::record('settings', "Updated leave type {$type->code}");

        return back()->with('success', 'Leave type updated.');
    }
}
