<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds formatted .xlsx downloads (templates and exports).
 * Sheet definition:
 *   title        sheet name
 *   header       column titles
 *   rows         iterable of arrays (strings are written as text so IDs / UAN numbers keep all digits)
 *   validations  [colIndex(0-based) => ['decimal' => [min, max]] | ['list' => [..]]]
 *   highlight    rows whose first cell matches these regex are styled as section / total rows
 *   instructions lines for an "Instructions" sheet (added after the data sheets)
 */
class XlsxBuilder
{
    private Spreadsheet $book;

    public function __construct()
    {
        $this->book = new Spreadsheet();
        $this->book->removeSheetByIndex(0);
        $this->book->getProperties()->setCreator('Payslip & HR Suite')->setCompany((string) setting('company_name'));
    }

    public function sheet(string $title, array $header, iterable $rows, array $opts = []): self
    {
        $ws = $this->book->createSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $title), 0, 31));
        $cols = count($header);
        $last = Coordinate::stringFromColumnIndex(max(1, $cols));

        foreach ($header as $i => $h) {
            $ws->setCellValue([$i + 1, 1], $h);
        }
        $ws->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_GRADIENT_LINEAR, 'rotation' => 90, 'startColor' => ['rgb' => '4F46E5'], 'endColor' => ['rgb' => '7C3AED']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getRowDimension(1)->setRowHeight(30);

        $r = 2;
        foreach ($rows as $row) {
            $row = array_values((array) $row);
            foreach ($row as $c => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                if (is_int($v) || is_float($v)) {
                    $ws->setCellValue([$c + 1, $r], $v);
                } else {
                    $ws->setCellValueExplicit([$c + 1, $r], (string) $v, DataType::TYPE_STRING);
                }
            }
            $first = (string) ($row[0] ?? '');
            $second = (string) ($row[1] ?? '');
            foreach ($opts['highlight'] ?? [] as $re => $color) {
                if (preg_match($re, $first) || preg_match($re, $second)) {
                    $ws->getStyle("A$r:$last$r")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]]]);
                }
            }
            $r++;
        }
        $lastRow = max(2, $r - 1);
        $ws->getStyle("A1:$last$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9DEE8');
        $ws->freezePane('A2');
        if (! empty($opts['autofilter'])) {
            $ws->setAutoFilter("A1:$last$lastRow");
        }
        foreach ($opts['number_format'] ?? [] as $col => $fmt) {
            $L = Coordinate::stringFromColumnIndex($col + 1);
            $ws->getStyle("{$L}2:$L$lastRow")->getNumberFormat()->setFormatCode($fmt);
        }

        // input rules shown in Excel while typing (applied to extra blank rows too)
        $validRows = max($lastRow, 2) + (int) ($opts['spare_rows'] ?? 0);
        foreach ($opts['validations'] ?? [] as $col => $rule) {
            $L = Coordinate::stringFromColumnIndex($col + 1);
            $dv = new DataValidation();
            $dv->setAllowBlank(true)->setShowErrorMessage(true)->setShowInputMessage(true)->setErrorStyle(DataValidation::STYLE_STOP);
            if (isset($rule['decimal'])) {
                [$min, $max] = $rule['decimal'];
                $dv->setType(DataValidation::TYPE_DECIMAL)->setOperator(DataValidation::OPERATOR_BETWEEN)
                    ->setFormula1((string) $min)->setFormula2((string) $max)
                    ->setErrorTitle('Invalid number')->setError("Enter a number between $min and $max.")
                    ->setPromptTitle($header[$col] ?? '')->setPrompt("Number from $min to $max");
            } elseif (isset($rule['list'])) {
                $dv->setType(DataValidation::TYPE_LIST)->setShowDropDown(true)
                    ->setFormula1('"'.implode(',', array_map(fn ($x) => str_replace([',', '"'], ' ', $x), $rule['list'])).'"')
                    ->setErrorTitle('Invalid value')->setError('Choose a value from the list.')
                    ->setPromptTitle($header[$col] ?? '')->setPrompt('Choose from the list');
            }
            $ws->setDataValidation("{$L}2:$L$validRows", $dv);
        }

        foreach (range(1, max(1, $cols)) as $c) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        if (! empty($opts['instructions'])) {
            $this->instructions($opts['instructions'], $opts['instructions_title'] ?? $title);
        }

        return $this;
    }

    private function instructions(array $lines, string $title): void
    {
        $ws = $this->book->createSheet();
        $ws->setTitle('Instructions');
        $ws->setCellValue('A1', 'How to fill: '.$title);
        $ws->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '4F46E5']]]);
        foreach (array_values($lines) as $i => $line) {
            $ws->setCellValueExplicit('A'.($i + 3), ($i + 1).'. '.$line, DataType::TYPE_STRING);
        }
        $ws->getColumnDimension('A')->setWidth(120);
        $ws->getStyle('A3:A'.(count($lines) + 3))->getAlignment()->setWrapText(true);
    }

    public function download(string $filename): BinaryFileResponse
    {
        $this->book->setActiveSheetIndex(0);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($this->book))->save($tmp);

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
