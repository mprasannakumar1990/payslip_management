<?php

namespace Tests\Feature;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\ImportService;
use App\Services\PayrollService;
use App\Services\SalarySheetImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Salary Workings Oct 2026.xls" — the layout used from October 2026 on. Skipped when the workbook is not present. */
class SalarySheetImportTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = base_path('docs/sheets/Salary Workings Oct 2026.xls');
        if (! is_file($this->file)) {
            $this->markTestSkipped('Salary Workings Oct 2026.xls not available');
        }
        $this->seed(DatabaseSeeder::class);
        $this->travelTo('2026-11-24 10:00');
    }

    private function parsed(): array
    {
        $r = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($this->file);
        $r->setReadDataOnly(true);
        $book = $r->load($this->file);

        return app(ImportService::class)->parseSalarySheet($book, 'October 2026');
    }

    public function test_layout_and_allowance_types_follow_the_october_2026_sheet(): void
    {
        $active = AllowanceType::ordered()->where('is_active', true)->pluck('name')->all();
        $this->assertSame(['Spl Allowance', 'Food', 'Transport', 'Food Welfare', 'Mobile', 'Old Increment', 'Increment 2026'], $active);
        // Old Increment = the four former increment columns added together (Sriram: 2790 + 3354 + 2511 + 1922)
        $sriram = Employee::where('emp_code', 'TSA006')->first();
        $old = AllowanceType::where('name', 'Old Increment')->first();
        $this->assertEquals(10577, $sriram->allowances()->where('allowance_type_id', $old->id)->value('amount'));
    }

    public function test_sheet_is_read_and_imported_with_new_employees_and_payslips(): void
    {
        $p = $this->parsed();
        $this->assertCount(36, $p['rows']);
        $this->assertSame(['Spl Allowance', 'Food', 'Transport', 'Food Welfare', 'Mobile', 'Old Increment', 'Increment 2026'], $p['allowances']);

        $svc = app(SalarySheetImporter::class);
        $a = $svc->analyse($p, 2026, 10);
        $new = collect($a['rows'])->where('action', 'create')->pluck('emp_code')->sort()->values()->all();
        $this->assertSame(['TSA128', 'TSA129', 'TSA130', 'TSA131'], $new);
        $this->assertSame([], collect($a['rows'])->flatMap(fn ($r) => $r['errors'])->all()); // MD (no Emp ID) is matched by name
        $vadivel = collect($a['rows'])->firstWhere('emp_code', 'TSA030');
        $this->assertStringContainsString('not valid', implode(' ', $vadivel['warnings'])); // joining date 2111 in the sheet is ignored

        $this->actingAs(User::where('role', 'super_admin')->first());
        $s = $svc->apply($a['rows'], 2026, 10, ['update_master' => true, 'create_new' => true, 'save_payroll' => true]);
        $this->assertSame(4, $s['created']);
        $this->assertSame(36, $s['payslips']);

        $run = PayrollRun::where('year', 2026)->where('month', 10)->first();
        $this->assertSame('draft', $run->status);
        // the sheet's grand total (1,057,524.52) leaves out the MD row (170,000); the payroll includes him
        $this->assertEqualsWithDelta(1057524.52 + 170000, $run->payslips()->sum('total_amount'), 1.0);
        $this->assertEqualsWithDelta(2684, $run->payslips()->sum('food_coupon'), 0.01);

        // master data now matches the sheet
        $karthika = Employee::where('emp_code', 'TSA129')->first();
        $this->assertSame('2025-12-19', $karthika->date_of_joining->toDateString());
        $this->assertEquals(10000, $karthika->other_allowance);
        $this->assertEquals(12000, $karthika->general_allowance);
        $this->assertSame('2011-03-01', Employee::where('emp_code', 'TSA030')->first()->date_of_joining->toDateString()); // kept
        $this->assertSame('100384574244', Employee::where('emp_code', 'TSA006')->first()->uan_no); // "0" in the sheet does not wipe it

        // future payroll is calculated in the same way as the sheet (Sriram: no absence, PF capped at 3,000)
        $nov = PayrollRun::create(['year' => 2026, 'month' => 11, 'working_days' => 26]);
        app(PayrollService::class)->generate($nov);
        $slip = $nov->payslips()->where('emp_code', 'TSA006')->first();
        $this->assertEquals(3000, $slip->pf);
        $this->assertEquals(round(54475.62), $slip->total_amount);
    }

    public function test_register_and_export_use_the_sheet_columns(): void
    {
        $svc = app(SalarySheetImporter::class);
        $this->actingAs(User::where('role', 'super_admin')->first());
        $svc->apply($svc->analyse($this->parsed(), 2026, 10)['rows'], 2026, 10, ['update_master' => true, 'create_new' => true, 'save_payroll' => true]);
        $run = PayrollRun::where('year', 2026)->where('month', 10)->first();

        $this->get(route('payroll.show', $run))->assertOk()
            ->assertSee('>Food Coupon<', false)->assertSee('>General Allowances<', false)->assertSee('>Increment 2026<', false);

        [$header] = \App\Support\SalaryRegisterExport::build($run);
        foreach (['Emp ID NO', 'hr (day/8hr)', 'Other Allowance', 'General Allowances', 'Gross Salary', 'Food Coupon', 'Net Salary',
            'Food Welfare', 'Attendance Bonus', 'Old Increment', 'Increment 2026', 'Birthday Welfare', 'Total Amount'] as $h) {
            $this->assertContains($h, $header);
        }
        $this->assertLessThan(array_search('Mobile', $header, true), array_search('Attendance Bonus', $header, true));

        // the export can be uploaded again with the same importer
        $this->get(route('payroll.export', $run))->assertOk();
    }
}
