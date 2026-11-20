<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Default values, used when a key has never been saved. */
    public const DEFAULTS = [
        'company_name' => 'TAESUNG AUTOIND PVT LTD',
        'company_address' => 'THIRUMAZHISAI, CHENNAI - 600124.',
        'working_days' => '26',
        'second_shift_rate' => '5',
        'third_shift_rate' => '6.875',
        'ot_multiplier' => '1.5',
        'ot_hours_per_day' => '8',
        'pf_percent' => '12',
        'pf_cap' => '1800',
        'esi_percent' => '0.75',
        'annual_leave_pool' => '28',
        'el_max_per_year' => '16',
        'gratuity_days' => '15',
        'gratuity_divisor' => '26',
        'gratuity_min_years' => '5',
        'krw_rate' => '14.75',
        'weekly_off' => '0', // 0 = Sunday (Carbon dayOfWeek)
        'payslip_footer' => 'I appreciate your hard work for a month.',
        'payroll_deadline_day' => '10',
        'backup_keep_days' => '30',
        'backup_copy_path' => '',
        'backup_reminder_enabled' => '1',
        'backup_reminder_time' => '16:00',
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => 'HR / Accounts',
        'mail_subject' => 'Payslip for {month} - {name}',
        'mail_body' => "Dear {name},

Please find attached your payslip for {month}.
Net pay: Rs. {amount}

Regards,
{company}",
        'ot_groups' => "HMI 1 & 2\nNISSAN 1 & 2\nENFIELD\nSEOYON\nPRODUCTION\nOTHERS\nMANAGEMENT",
    ];

    public static function allValues(): array
    {
        return array_merge(self::DEFAULTS, Cache::rememberForever('app_settings_saved', function () {
            return self::query()->pluck('value', 'key')->all();
        }));
    }

    public static function get(string $key, $default = null)
    {
        return self::allValues()[$key] ?? $default;
    }

    public static function num(string $key): float
    {
        return (float) self::get($key, 0);
    }

    public static function put(array $values): void
    {
        foreach ($values as $k => $v) {
            self::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        Cache::forget('app_settings_saved');
    }

    /** Apply SMTP settings saved in the app (Settings → Email) on top of .env. */
    public static function applyMailConfig(): void
    {
        $s = self::allValues();
        if (! empty($s['mail_host'])) {
            $password = '';
            if (! empty($s['mail_password'])) {
                try {
                    $password = \Illuminate\Support\Facades\Crypt::decryptString($s['mail_password']);
                } catch (\Throwable) {
                    $password = '';
                }
            }
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $s['mail_host'],
                'mail.mailers.smtp.port' => (int) $s['mail_port'],
                'mail.mailers.smtp.username' => $s['mail_username'] ?: null,
                'mail.mailers.smtp.password' => $password ?: null,
                'mail.mailers.smtp.scheme' => $s['mail_encryption'] === 'ssl' ? 'smtps' : 'smtp',
            ]);
        }
        if (! empty($s['mail_from_address'])) {
            config(['mail.from.address' => $s['mail_from_address'], 'mail.from.name' => $s['mail_from_name'] ?: $s['company_name']]);
        }
    }

    public static function mailConfigured(): bool
    {
        return config('mail.default') !== 'log' && config('mail.default') !== 'array' && ! empty(config('mail.from.address'))
            && config('mail.from.address') !== 'hello@example.com';
    }

    public static function otGroups(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) self::get('ot_groups')))));
    }
}
