<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Services\LeaveService;
use App\Services\PayrollService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Figures below are taken from "Salary Workings Nov 2025.xls" and "El Calculation 2025.xlsx". */
class PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // the Nov 2025 sheet capped PF at Rs 1,800 (from Oct 2026 the cap is Rs 3,000)
        \App\Models\Setting::put(['pf_cap' => '1800']);
    }

    private function calc(array $overrides): array
    {
        return app(PayrollService::class)->calculate(array_merge([
            'basic_salary' => 0, 'working_days' => 26, 'present_days' => 26, 'absent_days' => 0, 'lop_days' => 0,
            'second_shift_hours' => 0, 'third_shift_hours' => 0, 'ot_hours' => 0, 'ot_multiplier' => 1.5, 'spl_ot_amount' => 0,
            'manager_special' => 0, 'factory_special' => 0, 'tax' => 0, 'advance' => 0, 'other_deduction' => 0,
            'birthday_welfare' => 0, 'attendance_bonus' => null, 'attendance_bonus_applicable' => true,
            'esi_applicable' => true, 'pf_applicable' => true, 'allowances' => [],
        ], $overrides));
    }

    public function test_salary_matches_excel_for_prabaharan(): void
    {
        // TSA007: basic 15379, 2nd 90h, 3rd 72h, OT 36h, factory 1500, 1 day leave
        $r = $this->calc([
            'basic_salary' => 15379, 'absent_days' => 1, 'second_shift_hours' => 90, 'third_shift_hours' => 72, 'ot_hours' => 36,
            'factory_special' => 1500,
            'allowances' => [['name' => 'Food', 'amount' => 2000], ['name' => 'Transport', 'amount' => 1300], ['name' => '2022 Increment', 'amount' => 1260],
                ['name' => 'Mobile', 'amount' => 300], ['name' => 'Pandemic Allowance', 'amount' => 2388], ['name' => 'Increment 2024', 'amount' => 1134],
                ['name' => 'Increment 2025', 'amount' => 844]],
        ]);
        $this->assertEquals(450, $r['second_shift_amount']);
        $this->assertEquals(495, $r['third_shift_amount']);
        $this->assertEqualsWithDelta(3992.63, $r['ot_amount'], 0.01);
        $this->assertEqualsWithDelta(21816.63, $r['gross_salary'], 0.01);
        $this->assertEquals(1800, $r['pf']); // capped
        $this->assertEqualsWithDelta(163.62, $r['esi'], 0.01);
        $this->assertEquals(0, $r['attendance_bonus']); // had a leave day
        $this->assertEquals(29079, $r['total_amount']);
    }

    public function test_lop_reduces_basic_and_pf_for_driver(): void
    {
        // TSA099 Govindaraj: basic 17500, present 20 of 26, OT 28h at 1.2x
        $r = $this->calc(['basic_salary' => 17500, 'present_days' => 20, 'absent_days' => 6, 'ot_hours' => 28, 'ot_multiplier' => 1.2, 'second_shift_hours' => 16]);
        $this->assertEqualsWithDelta(13461.54, $r['basic_earned'], 0.01);
        $this->assertEqualsWithDelta(2826.92, $r['ot_amount'], 0.01);
        $this->assertEquals(1615, $r['pf']);
        $this->assertEqualsWithDelta(16368.46, $r['gross_salary'], 0.01);
    }

    public function test_attendance_bonus_is_one_day_pay_without_absence(): void
    {
        $r = $this->calc(['basic_salary' => 21350]);
        $this->assertEqualsWithDelta(821.15, $r['attendance_bonus'], 0.01);
    }

    public function test_leave_beyond_annual_pool_becomes_lop(): void
    {
        $this->seed(DatabaseSeeder::class);
        $emp = Employee::where('emp_code', 'TSA099')->firstOrFail();
        // seeded register for 2025: 37 days used Jan–Oct, 6 in Nov  => all 6 Nov days are LOP (pool 28)
        $lop = app(LeaveService::class)->lopForMonth($emp, 2025, 11);
        $this->assertEquals(6, $lop['absent']);
        $this->assertEquals(6, $lop['lop']);
    }

    public function test_generated_november_payroll_matches_excel_for_unedited_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $run = PayrollRun::create(['year' => 2025, 'month' => 11, 'working_days' => 26]);
        app(PayrollService::class)->generate($run);
        $expected = ['TSA006' => 53723, 'TSA008' => 54415, 'TSA011' => 33644, 'TSA084' => 30772, 'TSA099' => 24702, 'TSA120' => null];
        foreach ($expected as $code => $total) {
            $slip = $run->payslips()->where('emp_code', $code)->firstOrFail();
            if ($total) {
                $this->assertEquals($total, $slip->total_amount, "Total for $code");
            }
        }
        // P Jayaprakash crossed the 28-day pool by 1 day in November
        $this->assertEquals(25, $run->payslips()->where('emp_code', 'TSA120')->value('present_days'));
    }

    public function test_el_credit_is_capped_and_prorated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $leave = app(LeaveService::class);
        $emp = Employee::create(['emp_code' => 'T1', 'name' => 'T', 'date_of_joining' => '2024-07-01', 'basic_salary' => 10000]);
        $this->assertEquals(8, $leave->elCreditComputed($emp, 2024)); // half year, nothing used => 16 * 6/12
        LeaveAdjustment::create(['employee_id' => $emp->id, 'year' => 2025, 'month' => 3, 'days' => 20]);
        $this->assertEquals(8, $leave->elCreditComputed($emp, 2025)); // 28 - 20
        LeaveAdjustment::create(['employee_id' => $emp->id, 'year' => 2025, 'month' => 4, 'days' => 10]);
        $this->assertEquals(0, $leave->elCreditComputed($emp, 2025)); // exceeded pool
    }

    public function test_pending_ot_is_not_paid(): void
    {
        $this->seed(DatabaseSeeder::class);
        $emp = Employee::where('emp_code', 'TSA084')->firstOrFail();
        OtEntry::create(['employee_id' => $emp->id, 'year' => 2026, 'month' => 1, 'normal_ot_hours' => 50, 'status' => 'pending']);
        $run = PayrollRun::create(['year' => 2026, 'month' => 1, 'working_days' => 26]);
        app(PayrollService::class)->generate($run);
        $this->assertEquals(0, $run->payslips()->where('employee_id', $emp->id)->value('ot_amount'));
    }
}
