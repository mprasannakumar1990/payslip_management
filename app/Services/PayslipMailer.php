<?php

namespace App\Services;

use App\Mail\PayslipMail;
use App\Models\Payslip;
use Illuminate\Support\Facades\Mail;

class PayslipMailer
{
    /** True after a failure that will affect every email (wrong SMTP login, server unreachable). */
    public bool $fatal = false;

    /** Send one payslip to the employee's email; the outcome is stored on the payslip. */
    public function send(Payslip $p): bool
    {
        $p->loadMissing('run', 'employee');
        $to = trim((string) $p->employee?->email);
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $p->update(['email_error' => 'No valid email address on the employee record.']);

            return false;
        }
        try {
            Mail::to($to, $p->name)->send(new PayslipMail($p));
            $p->update(['emailed_at' => now(), 'emailed_to' => $to, 'email_error' => null]);

            return true;
        } catch (\Throwable $e) {
            [$message, $this->fatal] = self::explain($e->getMessage());
            $p->update(['email_error' => mb_substr($message, 0, 480)]);

            return false;
        }
    }

    /** Turn SMTP errors into a message the office can act on. Returns [message, isFatal]. */
    public static function explain(string $raw): array
    {
        $host = (string) setting('mail_host');
        if (preg_match('/Failed to authenticate|535|Username and Password not accepted|BadCredentials/i', $raw)) {
            $hint = 'Email login failed. In Settings → Email, the Username must be the full email address';
            if (stripos($host, 'gmail') !== false) {
                $hint .= ' (e.g. name@gmail.com) and the Password must be a 16-character Gmail App Password (Google Account → Security → 2-Step Verification → App passwords), not your normal Gmail password.';
            } elseif (preg_match('/office365|outlook/i', $host)) {
                $hint .= ' and SMTP AUTH must be enabled for the mailbox in Microsoft 365 (an app password is needed if MFA is on).';
            } else {
                $hint .= ' and the password must be correct.';
            }

            return [$hint, true];
        }
        if (preg_match('/Connection could not be established|getaddrinfo|timed out|Connection refused|Unable to connect/i', $raw)) {
            return ['Could not connect to the email server. Check the SMTP host, port and encryption in Settings → Email (Gmail: smtp.gmail.com, port 587, TLS).', true];
        }

        return [$raw, false];
    }
}
