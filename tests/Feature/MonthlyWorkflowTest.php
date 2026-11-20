<?php

namespace Tests\Feature;

use App\Mail\PayslipMail;
use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\OtEntry;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\EmployeeMatcher;
use App\Services\ImportService;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\WorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MonthlyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_working_period_follows_the_10th_deadline_across_the_year_change(): void
    {
        $this->travelTo('2027-01-05 10:00');
        $this->assertSame('2026-12', working_period()->format('Y-m'));
        $this->travelTo('2027-01-11 10:00');
        $this->assertSame('2027-01', working_period()->format('Y-m'));
        $this->assertContains(2028, year_options()); // next year is always selectable
    }

    public function test_new_year_starts_with_a_fresh_leave_pool_and_previous_year_el_is_credited(): void
    {
        $this->travelTo('2027-02-01');
        $emp = Employee::where('emp_code', 'TSA084')->first();
        LeaveAdjustment::create(['employee_id' => $emp->id, 'year' => 2026, 'month' => 5, 'days' => 10]);
        $leave = app(LeaveService::class);
        $this->assertEquals(28, $leave->pool($emp, 2027));
        $this->assertEquals(28, $leave->balances($emp, 2027)['balance']);
        $this->assertEquals(16, $leave->elCreditComputed($emp, 2026)); // 28 - 10 = 18, capped at 16
        $this->assertTrue(collect($leave->elHistory($emp))->contains(fn ($h) => $h['year'] === 2027));
    }

    public function test_payroll_for_january_2027_generates_and_emails(): void
    {
        Mail::fake();
        $this->travelTo('2027-02-03');
        $emp = Employee::where('emp_code', 'TSA084')->first();
        $emp->update(['email' => 'jp@example.com']);
        OtEntry::create(['employee_id' => $emp->id, 'year' => 2027, 'month' => 1, 'normal_ot_hours' => 10, 'status' => 'approved']);
        $admin = User::where('role', 'admin')->first();

        $this->actingAs($admin)->post('/payroll', ['period' => '2027-01', 'working_days' => 26])->assertRedirect();
        $run = PayrollRun::where('year', 2027)->where('month', 1)->firstOrFail();
        $this->assertGreaterThan(30, $run->payslips()->count());

        // cannot email before finalizing
        $this->actingAs($admin)->post("/payroll/{$run->id}/email")->assertSessionHas('error');
        Mail::assertNothingSent();

        $this->actingAs($admin)->post("/payroll/{$run->id}/finalize");
        $this->actingAs($admin)->post("/payroll/{$run->id}/email")->assertSessionHas('success');
        Mail::assertSent(PayslipMail::class, 1);
        Mail::assertSent(PayslipMail::class, fn ($m) => $m->hasTo('jp@example.com'));
        $this->assertNotNull($run->payslips()->where('employee_id', $emp->id)->value('emailed_at'));
        $this->assertSame(1, $run->payslips()->whereNotNull('emailed_at')->count());

        // a second "email all" sends nothing new
        $this->actingAs($admin)->post("/payroll/{$run->id}/email");
        Mail::assertSent(PayslipMail::class, 1);

        $w = app(WorkflowService::class)->status(2027, 1);
        $this->assertSame('done', collect($w['steps'])->firstWhere('key', 'finalize')['state']);
        $this->assertSame('attention', collect($w['steps'])->firstWhere('key', 'email')['state']);
    }

    public function test_workflow_flags_data_changed_after_generation(): void
    {
        $this->travelTo('2026-03-05');
        $run = PayrollRun::create(['year' => 2026, 'month' => 2, 'working_days' => 26]);
        app(PayrollService::class)->generate($run);
        $this->travel(1)->minutes();
        OtEntry::create(['employee_id' => Employee::first()->id, 'year' => 2026, 'month' => 2, 'normal_ot_hours' => 5, 'status' => 'approved']);
        $w = app(WorkflowService::class)->status(2026, 2);
        $this->assertSame('attention', collect($w['steps'])->firstWhere('key', 'generate')['state']);
    }

    public function test_ot_sheet_from_excel_is_parsed_and_matched(): void
    {
        $svc = app(ImportService::class);
        $path = base_path('docs/sheets/OT Sheet April 2024 to March 2025.xls');
        $book = $svc->load(new UploadedFile($path, basename($path), null, null, true));
        $this->assertSame('Nov 2025', $svc->guessSheet($book, 2025, 11));
        $rows = $svc->parseOt($book, 'Nov 2025');
        $this->assertCount(32, $rows);
        $m = new EmployeeMatcher();
        $dharni = collect($rows)->firstWhere('name', 'Dharni');
        [$e] = $m->match($dharni['name'], null);
        $this->assertSame('TSA070', $e->emp_code); // via saved alias
        $this->assertEquals(98, $dharni['normal']);
    }

    public function test_ot_import_requires_verification_and_saves_only_ticked_valid_rows(): void
    {
        $admin = User::where('role', 'admin')->first();
        $a = Employee::where('emp_code', 'TSA084')->first();
        $b = Employee::where('emp_code', 'TSA099')->first();
        $rows = [
            ['include' => 1, 'employee_id' => $a->id, 'normal' => 12, 'second' => 0, 'third' => 0, 'spl' => 0, 'how' => 'id'],
            ['include' => 0, 'employee_id' => $b->id, 'normal' => -5, 'how' => 'id'], // error row left unticked
        ];
        $this->actingAs($admin)->post('/imports/ot', ['period' => '2026-04', 'status' => 'approved', 'rows' => $rows])
            ->assertSessionHasErrors('verified');
        $this->assertSame(0, OtEntry::where('year', 2026)->where('month', 4)->count());

        $this->actingAs($admin)->post('/imports/ot', ['period' => '2026-04', 'status' => 'approved', 'rows' => $rows, 'verified' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, OtEntry::where('year', 2026)->where('month', 4)->count());
        $this->assertEquals(12, OtEntry::where('employee_id', $a->id)->where('year', 2026)->where('month', 4)->value('normal_ot_hours'));

        // a ticked row with an invalid value is rejected on the server too
        $rows[1]['include'] = 1;
        $this->actingAs($admin)->post('/imports/ot', ['period' => '2026-05', 'status' => 'approved', 'rows' => $rows, 'verified' => 1])
            ->assertSessionHasErrors();
    }

    public function test_templates_download_as_xlsx(): void
    {
        $admin = User::where('role', 'admin')->first();
        foreach (['/imports/template/ot?period=2026-01', '/imports/template/leave?year=2026', '/employees/export'] as $url) {
            $res = $this->actingAs($admin)->get($url);
            $res->assertOk();
            $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));
        }
    }

    public function test_filters_on_list_pages(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->get('/employees?q=Gopi')->assertSee('TSA097')->assertDontSee('TSA006');
        $this->actingAs($admin)->get('/employees?email=missing')->assertSee('TSA006');
        $this->actingAs($admin)->get('/employees?group=SEOYON')->assertSee('Aswartha')->assertDontSee('Gopi');
    }
}
