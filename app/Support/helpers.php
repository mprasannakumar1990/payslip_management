<?php

if (! function_exists('inr')) {
    /** Format a number with Indian digit grouping, e.g. 1234567.5 => 12,34,567.50 */
    function inr($value, int $decimals = 2): string
    {
        $value = round((float) $value, $decimals);
        $neg = $value < 0;
        $value = abs($value);
        $parts = explode('.', number_format($value, $decimals, '.', ''));
        $int = $parts[0];
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }
        $out = $int.($decimals > 0 ? '.'.$parts[1] : '');

        return ($neg ? '-' : '').$out;
    }
}

if (! function_exists('num_clean')) {
    /** Show a quantity without trailing zeros: 26.00 => 26, 3.50 => 3.5 */
    function num_clean($value): string
    {
        $s = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }
}

if (! function_exists('amount_in_words')) {
    /** Indian-system amount in words, e.g. 106918 => "One Lakh Six Thousand Nine Hundred and Eighteen" */
    function amount_in_words($amount): string
    {
        $amount = (int) round((float) $amount);
        if ($amount === 0) {
            return 'Zero';
        }
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = function (int $n) use ($ones, $tens): string {
            return $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
        };
        $words = [];
        foreach ([['Crore', 10000000], ['Lakh', 100000], ['Thousand', 1000]] as [$name, $div]) {
            if ($amount >= $div) {
                $chunk = intdiv($amount, $div);
                $words[] = ($chunk >= 100 ? amount_in_words($chunk) : $two($chunk)).' '.$name;
                $amount %= $div;
            }
        }
        if ($amount >= 100) {
            $words[] = $ones[intdiv($amount, 100)].' Hundred';
            $amount %= 100;
        }
        if ($amount > 0) {
            $words[] = ($words ? 'and ' : '').$two($amount);
        }

        return implode(' ', $words);
    }
}

if (! function_exists('month_name')) {
    function month_name(int $m, bool $short = false): string
    {
        return \Illuminate\Support\Carbon::create(2000, $m, 1)->format($short ? 'M' : 'F');
    }
}

if (! function_exists('csv_download')) {
    /** Stream a CSV file (opens directly in Excel). $rows is iterable of arrays. */
    function csv_download(string $filename, array $header, iterable $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            if ($header) {
                fputcsv($out, $header);
            }
            foreach ($rows as $row) {
                // neutralise spreadsheet formula injection
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

if (! function_exists('xlsx_download')) {
    /** Single-sheet formatted Excel download. See App\Support\XlsxBuilder for $opts. */
    function xlsx_download(string $filename, array $header, iterable $rows, array $opts = []): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $title = $opts['title'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $opts['autofilter'] ??= true;

        return (new \App\Support\XlsxBuilder())->sheet($title, $header, $rows, $opts)->download($filename);
    }
}

if (! function_exists('year_options')) {
    /** Years for dropdowns: from the earliest joining year (or data year) up to next year — grows automatically. */
    function year_options(): array
    {
        static $years;
        if ($years === null) {
            $first = \App\Models\Employee::withTrashed()->min('date_of_joining');
            $min = $first ? (int) substr((string) $first, 0, 4) : (int) now()->year;
            $min = min($min, (int) (\App\Models\LeaveAdjustment::min('year') ?: $min), (int) (\App\Models\PayrollRun::min('year') ?: $min));
            $years = range((int) now()->year + 1, $min);
        }

        return $years;
    }
}

if (! function_exists('working_period')) {
    /**
     * The month the office is currently working on. Payslips for a month are generated between the 1st and the
     * deadline day (default 10th) of the following month, so until the deadline this is the previous month.
     */
    function working_period(): \Illuminate\Support\Carbon
    {
        $deadline = (int) (\App\Models\Setting::get('payroll_deadline_day', 10) ?: 10);
        $now = now();

        return ($now->day <= $deadline ? $now->copy()->subMonthNoOverflow() : $now->copy())->startOfMonth();
    }
}

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return \App\Models\Setting::get($key, $default);
    }
}
