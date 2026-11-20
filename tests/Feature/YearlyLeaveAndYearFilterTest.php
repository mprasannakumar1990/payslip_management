<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\LeaveService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearlyLeaveAndYearFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'super_admin')->first();
    }

    private function emp(array $attrs = []): Employee
    {
        return Employee::create($attrs + ['emp_code' => 'T'.random_int(1000, 9999), 'name' => 'Test Person', 'basic_salary' => 26000,
            'date_of_joining' => '2020-01-01', 'status' => 'active']);
    }

    public function test_first_visit_of_a_new_year_generates_leave_for_everyone(): void
    {
        $this->travelTo('2027-01-02 09:00');
        $this->assertFalse(LeaveAllocation::where('year', 2027)->exists());

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $onRolls = Employee::onRollsIn(2027)->count();
        $paidTypes = LeaveType::where('is_paid', true)->count();
        $this->assertSame($onRolls * $paidTypes, LeaveAllocation::where('year', 2027)->count());
        $this->assertTrue(ActivityLog::where('description', 'like', '%generated automatically for 2027%')->exists());
    }

    public function test_generated_leave_is_pro_rated_and_drives_the_balance(): void
    {
        $leave = app(LeaveService::class);
        $full = $this->emp();
        $joiner = $this->emp(['date_of_joining' => '2027-07-15']); // Jul - Dec = 6 months

        $leave->generateYear(2027);
        $this->assertEquals(28, $leave->pool($full, 2027));
        $this->assertEquals(14, app(LeaveService::class)->pool($joiner, 2027));
        $el = LeaveType::where('code', 'EL')->first();
        $this->assertEquals(8, LeaveAllocation::where(['employee_id' => $joiner->id, 'year' => 2027, 'leave_type_id' => $el->id])->value('days'));

        // admin raises EL to 20 for one employee: the pool follows, and regeneration keeps the edit
        $this->actingAs($this->admin)->put(route('leave.allocations.update', $full), ['year' => 2027, 'days' => [$el->id => 20]])->assertRedirect();
        $this->assertEquals(32, app(LeaveService::class)->pool($full, 2027));
        app(LeaveService::class)->generateYear(2027);
        $this->assertEquals(32, app(LeaveService::class)->pool($full, 2027));

        // reset goes back to the standard entitlement
        $this->actingAs($this->admin)->put(route('leave.allocations.update', $full), ['year' => 2027, 'reset' => 1]);
        $this->assertEquals(28, app(LeaveService::class)->pool($full, 2027));
    }

    public function test_new_and_exiting_employees_get_their_allocation_updated(): void
    {
        app(LeaveService::class)->generateYear(2027);
        $e = $this->emp(['date_of_joining' => '2027-04-01']); // Apr - Dec = 9 months
        $this->assertEquals(21, app(LeaveService::class)->pool($e, 2027));

        $e->update(['date_of_exit' => '2027-06-30', 'status' => 'exited']); // Apr - Jun = 3 months
        $this->assertEquals(7, app(LeaveService::class)->pool($e, 2027));
    }

    public function test_generate_button_and_allocation_page(): void
    {
        // the result message is shown as a top-right toast
        $this->actingAs($this->admin)->followingRedirects()->post(route('leave.allocations.generate'), ['year' => 2026])
            ->assertOk()->assertSee('id="toastStack"', false)->assertSee('Leave for 2026 generated');
        $this->assertTrue(LeaveAllocation::where('year', 2026)->exists());
        $this->actingAs($this->admin)->get(route('leave.allocations', ['year' => 2026]))->assertOk()->assertSee('Yearly Leave Allocation');
        $this->actingAs($this->admin)->get(route('leave.allocations', ['year' => 2026, 'export' => 1]))->assertOk();
    }

    public function test_year_switch_applies_to_every_page(): void
    {
        $this->travelTo('2027-03-15');
        $this->actingAs($this->admin)->from(route('payroll.index', ['year' => 2027]))
            ->post(route('year.switch'), ['year' => 2026])->assertRedirect(route('payroll.index'));
        $this->assertSame(2026, active_year());

        PayrollRun::create(['year' => 2026, 'month' => 5, 'working_days' => 26]);
        PayrollRun::create(['year' => 2027, 'month' => 1, 'working_days' => 26]);
        $this->actingAs($this->admin)->get(route('payroll.index'))->assertOk()->assertSee('May 2026')->assertDontSee('January 2027');

        $emp = Employee::first();
        $type = LeaveType::first();
        LeaveRequest::create(['employee_id' => $emp->id, 'leave_type_id' => $type->id, 'from_date' => '2026-06-03', 'to_date' => '2026-06-03', 'days' => 1, 'status' => 'pending', 'reason' => 'old-year-leave']);
        LeaveRequest::create(['employee_id' => $emp->id, 'leave_type_id' => $type->id, 'from_date' => '2027-02-03', 'to_date' => '2027-02-03', 'days' => 1, 'status' => 'pending', 'reason' => 'new-year-leave']);
        $this->actingAs($this->admin)->get(route('leave-requests.index'))->assertSee('old-year-leave')->assertDontSee('new-year-leave');
        $this->actingAs($this->admin)->get(route('leave-requests.index', ['year' => 2027]))->assertSee('new-year-leave')->assertDontSee('old-year-leave');
        $this->assertSame(2027, active_year()); // a page opened with ?year= switches the whole app
    }

    public function test_records_are_saved_in_ist_and_header_shows_the_live_clock(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-15 09:00:00', 'UTC')); // = 14:30 IST
        $emp = $this->emp();
        $raw = \Illuminate\Support\Facades\DB::table('employees')->where('id', $emp->id)->value('created_at');
        $this->assertSame('2027-01-15 14:30:00', (string) $raw);

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('id="liveClock"', false)->assertSee('data-server-ms="'.now()->getTimestampMs().'"', false);
    }

    public function test_every_page_renders_for_past_current_and_next_year(): void
    {
        $this->travelTo('2027-03-15');
        $emp = Employee::first();
        foreach ([2026, 2027, 2028] as $year) {
            $this->actingAs($this->admin)->post(route('year.switch'), ['year' => $year]);
            foreach (['dashboard', 'workflow.index', 'imports.index', 'employees.index', 'leave.allocations', 'leave-requests.index',
                'leave.balances', 'leave.register', 'leave.el-summary', 'leave.adjustments', 'ot.index', 'ot.report', 'ot.history',
                'payroll.index', 'settlements.index', 'activity-logs.index'] as $route) {
                $this->actingAs($this->admin)->get(route($route))->assertOk();
            }
            $this->actingAs($this->admin)->get(route('employees.show', $emp))->assertOk()->assertSee("Payslips — $year");
        }
    }
}
