<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Reads the office's existing Excel formats (and the CSV templates the app hands out):
 *  - OT sheet:        "Sl No | Name | 2nd Shift | 3rd Shift | Normal OT | Spl OT | Total OT | Remarks" (one sheet per month)
 *  - Leave register:  "Sl No | Name | Date of Joining | Jan ... Dec | Total Leave ..." (one sheet per year)
 *  - Employees:       the Employees export (Emp ID, Name, Designation, ..., allowance columns)
 * Columns are located by header text, so extra / re-ordered columns are fine.
 */
class ImportService
{
    public const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    public function load(UploadedFile $file): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);

        return $reader->load($file->getRealPath());
    }

    public function sheetNames(Spreadsheet $book): array
    {
        return $book->getSheetNames();
    }

    /** Pick the sheet whose name refers to the given month ("Nov 2025", "November 2025", "2025-11") or year ("2026"). */
    public function guessSheet(Spreadsheet $book, ?int $year, ?int $month = null): string
    {
        foreach ($book->getSheetNames() as $name) {
            $n = strtolower(trim($name));
            if ($month === null) {
                if ($year && preg_match('/\b'.$year.'\b/', $n) && ! preg_match('/\bto\b/', $n)) {
                    return $name;
                }

                continue;
            }
            $d = Carbon::create($year, $month, 1);
            $forms = [strtolower($d->format('M Y')), strtolower($d->format('F Y')), $d->format('Y-m'), strtolower($d->format('M-Y')),
                strtolower($d->format('M y')), strtolower($d->format('M')).' '.$year];
            if ($month === 9) {
                $forms[] = 'sept '.$year;
            }
            if (in_array($n, $forms, true) || (str_contains($n, (string) $year) && str_starts_with($n, strtolower($d->format('M'))))) {
                return $name;
            }
        }

        return $book->getActiveSheet()->getTitle();
    }

    private function rows(Spreadsheet $book, string $sheet): array
    {
        $ws = $book->getSheetByName($sheet) ?? $book->getActiveSheet();

        return $ws->toArray(null, true, false, false);
    }

    /** Empty cells, numbers and "-" (used in the sheets for nil) are accepted; anything else is flagged. */
    public static function isNumberOrBlank($v): bool
    {
        if ($v === null || is_int($v) || is_float($v)) {
            return true;
        }
        $t = trim((string) $v);

        return $t === '' || $t === '-' || is_numeric(str_replace(',', '', $t));
    }

    private static function num($v): float
    {
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }
        $v = preg_replace('/[^0-9.\-]/', '', (string) $v);

        return is_numeric($v) ? round((float) $v, 2) : 0.0;
    }

    private static function text($v): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $v));
    }

    /** Find the header row: the first row in which every pattern matches some cell. Returns [rowIndex, [key => colIndex]]. */
    private function header(array $rows, array $patterns, array $required): ?array
    {
        foreach (array_slice($rows, 0, 30, true) as $i => $row) {
            $cols = [];
            foreach ($row as $c => $cell) {
                $t = strtolower(self::text($cell));
                if ($t === '') {
                    continue;
                }
                foreach ($patterns as $key => $re) {
                    if (! isset($cols[$key]) && preg_match($re, $t)) {
                        $cols[$key] = $c;
                        break;
                    }
                }
            }
            if (count(array_intersect($required, array_keys($cols))) === count($required)) {
                return [$i, $cols];
            }
        }

        return null;
    }

    /** OT rows of one month. */
    public function parseOt(Spreadsheet $book, string $sheet): array
    {
        $rows = $this->rows($book, $sheet);
        $h = $this->header($rows, [
            'code' => '/^(emp\.?\s*(id|code|no)|id\s*no|code)$/', 'name' => '/^(name|employee|employee name)$/',
            'second' => '/2nd/', 'third' => '/3rd/', 'normal' => '/normal/', 'spl' => '/^(spl|special)/', 'remarks' => '/remark/',
        ], ['name', 'normal']);
        if (! $h) {
            throw new \RuntimeException("Could not find the header row (Name, 2nd Shift, 3rd Shift, Normal OT …) in sheet \"$sheet\".");
        }
        [$start, $c] = $h;
        $out = [];
        foreach (array_slice($rows, $start + 1) as $row) {
            $first = strtolower(self::text($row[0] ?? ''));
            if ($first === 'summary') {
                break;
            }
            $name = self::text($row[$c['name']] ?? '');
            $code = isset($c['code']) ? self::text($row[$c['code']] ?? '') : '';
            if (($name === '' && $code === '') || preg_match('/^(total|particulars|grand total)$/i', $name) || preg_match('/^total$/i', $first)) {
                continue;
            }
            $r = ['name' => $name, 'code' => $code, 'invalid' => [],
                'remarks' => isset($c['remarks']) ? mb_substr(self::text($row[$c['remarks']] ?? ''), 0, 200) : ''];
            foreach (['second' => '2nd Shift', 'third' => '3rd Shift', 'normal' => 'Normal OT', 'spl' => 'Spl OT'] as $k => $label) {
                $raw = isset($c[$k]) ? ($row[$c[$k]] ?? null) : null;
                if (! self::isNumberOrBlank($raw)) {
                    $r['invalid'][] = "$label \"".mb_substr((string) $raw, 0, 20).'"';
                }
                $r[$k] = self::num($raw ?? 0);
            }
            $r['line'] = null;
            $out[] = $r;
        }

        return $out;
    }

    /** Monthly leave usage of one year (leave register sheet). */
    public function parseLeaveRegister(Spreadsheet $book, string $sheet): array
    {
        $rows = $this->rows($book, $sheet);
        $patterns = ['code' => '/^(emp\.?\s*(id|code|no)|id\s*no|code)$/', 'name' => '/^(name|employee|employee name)$/'];
        foreach (self::MONTHS as $i => $m) {
            $patterns['m'.($i + 1)] = '/^'.$m.'/';
        }
        $h = $this->header($rows, $patterns, ['name', 'm1', 'm12']);
        if (! $h) {
            throw new \RuntimeException("Could not find the header row (Name, Jan … Dec) in sheet \"$sheet\".");
        }
        [$start, $c] = $h;
        $out = [];
        foreach (array_slice($rows, $start + 1) as $row) {
            $name = self::text($row[$c['name']] ?? '');
            $code = isset($c['code']) ? self::text($row[$c['code']] ?? '') : '';
            if (($name === '' && $code === '') || preg_match('/total/i', $name)) {
                continue;
            }
            $months = [];
            $invalid = [];
            for ($m = 1; $m <= 12; $m++) {
                $v = $row[$c['m'.$m]] ?? null;
                if (! self::isNumberOrBlank($v)) {
                    $invalid[$m] = (string) $v;
                }
                $months[$m] = ($v === null || trim((string) $v) === '') ? null : self::num($v);
            }
            $out[] = ['name' => $name, 'code' => $code, 'months' => $months, 'invalid' => $invalid];
        }

        return $out;
    }

    /** Employee master rows (the Employees export / template). */
    public function parseEmployees(Spreadsheet $book, array $allowanceNames): array
    {
        $rows = $this->rows($book, $book->getActiveSheet()->getTitle());
        $patterns = [
            'emp_code' => '/^emp\.?\s*(id|code)$/', 'name' => '/^name$/', 'designation' => '/^designation$/', 'grade' => '/^grade$/',
            'ot_group' => '/^(plant|ot group|plant \/ group)/', 'date_of_joining' => '/joining/', 'date_of_exit' => '/exit/',
            'uan_no' => '/(epf|uan)/', 'esic_no' => '/esic/', 'basic_salary' => '/^basic/', 'manager_special' => '/manager/',
            'factory_special' => '/factory/', 'monthly_tax' => '/tax/', 'pf_applicable' => '/^pf$/', 'esi_applicable' => '/^esi$/',
            'status' => '/^status$/', 'email' => '/^e-?mail/', 'phone' => '/^(phone|mobile no)/', 'bank_account_no' => '/account/',
            'bank_ifsc' => '/ifsc/', 'pan_no' => '/^pan/',
        ];
        foreach ($allowanceNames as $i => $a) {
            $patterns['al'.$i] = '/^'.preg_quote(strtolower($a), '/').'$/';
        }
        $h = $this->header($rows, $patterns, ['emp_code', 'name']);
        if (! $h) {
            throw new \RuntimeException('Could not find the header row (Emp ID, Name, …). Use the Employees export as the template.');
        }
        [$start, $c] = $h;
        $out = [];
        foreach (array_slice($rows, $start + 1) as $row) {
            $code = self::text($row[$c['emp_code']] ?? '');
            $name = self::text($row[$c['name']] ?? '');
            if ($code === '' || $name === '') {
                continue;
            }
            $r = ['emp_code' => $code, 'name' => $name, 'allowances' => []];
            foreach (['designation', 'grade', 'ot_group', 'uan_no', 'esic_no', 'status', 'email', 'phone', 'bank_account_no', 'bank_ifsc', 'pan_no'] as $f) {
                if (isset($c[$f])) {
                    $r[$f] = self::text($row[$c[$f]] ?? '');
                }
            }
            foreach (['basic_salary', 'manager_special', 'factory_special', 'monthly_tax'] as $f) {
                if (isset($c[$f])) {
                    $r[$f] = self::num($row[$c[$f]] ?? 0);
                }
            }
            foreach (['pf_applicable', 'esi_applicable'] as $f) {
                if (isset($c[$f])) {
                    $r[$f] = in_array(strtolower(self::text($row[$c[$f]] ?? '')), ['yes', 'y', '1', 'true'], true);
                }
            }
            foreach (['date_of_joining', 'date_of_exit'] as $f) {
                if (isset($c[$f])) {
                    $r[$f] = self::date($row[$c[$f]] ?? null);
                }
            }
            foreach ($allowanceNames as $i => $a) {
                if (isset($c['al'.$i])) {
                    $r['allowances'][$a] = self::num($row[$c['al'.$i]] ?? 0);
                }
            }
            if (isset($r['uan_no'])) {
                $r['uan_no'] = preg_replace('/\.0+$/', '', $r['uan_no']);
            }
            if (isset($r['esic_no'])) {
                $r['esic_no'] = preg_replace('/\.0+$/', '', $r['esic_no']);
            }
            $out[] = $r;
        }

        return $out;
    }

    public static function date($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && $v > 1000) {
            return Carbon::instance(XlDate::excelToDateTimeObject((float) $v))->toDateString();
        }
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.Y', 'd-M-y', 'd-M-Y', 'j-M-y'] as $f) {
            try {
                $d = Carbon::createFromFormat('!'.$f, trim((string) $v));
                if ($d && $d->year > 1950) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
