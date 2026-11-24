<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotosCelebrationsAndRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'super_admin')->first();
    }

    private function payload(Employee $e, array $extra = []): array
    {
        return $extra + [
            'emp_code' => $e->emp_code, 'name' => $e->name, 'date_of_joining' => $e->date_of_joining->toDateString(),
            'basic_salary' => $e->basic_salary, 'ot_multiplier' => $e->ot_multiplier, 'status' => $e->status,
        ];
    }

    public function test_photo_upload_is_private_and_shown(): void
    {
        Storage::fake('local');
        $e = Employee::first();
        $this->actingAs($this->admin)->put(route('employees.update', $e), $this->payload($e, ['photo' => UploadedFile::fake()->createWithContent('me.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))]))
            ->assertRedirect();
        $e->refresh();
        $this->assertNotNull($e->photo_path);
        Storage::disk('local')->assertExists($e->photo_path);

        $this->actingAs($this->admin)->get(route('employees.photo', $e))->assertOk();
        $this->actingAs($this->admin)->get(route('employees.index'))->assertSee(route('employees.photo', $e), false);
        auth()->logout();
        $this->get(route('employees.photo', $e))->assertRedirect(route('login')); // not public

        // removing deletes the file
        $old = $e->photo_path;
        $this->actingAs($this->admin)->put(route('employees.update', $e), $this->payload($e, ['remove_photo' => 1]));
        $this->assertNull($e->fresh()->photo_path);
        Storage::disk('local')->assertMissing($old);
    }

    public function test_non_images_are_rejected(): void
    {
        $e = Employee::first();
        $this->actingAs($this->admin)->put(route('employees.update', $e), $this->payload($e, ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('photo');
    }

    public function test_dashboard_wishes_birthdays_and_anniversaries(): void
    {
        $this->travelTo('2027-03-15 10:00');
        $bday = Employee::first();
        $bday->update(['date_of_birth' => '1990-03-15', 'status' => 'active']);
        $soon = Employee::skip(1)->first();
        $soon->update(['date_of_birth' => '1992-03-20', 'date_of_joining' => '2020-03-25', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Happy Birthday, '.$bday->name, false)
            ->assertSee('turns 37')                 // age shown
            ->assertSee('in 5 days')                // upcoming birthday
            ->assertSee('completes <strong>7 years</strong>', false); // work anniversary on 25 Mar
    }

    public function test_register_shows_only_filled_columns_and_payslip_only_applicable_lines(): void
    {
        $run = PayrollRun::create(['year' => 2026, 'month' => 10, 'working_days' => 26]);
        app(PayrollService::class)->generate($run);
        $run->payslips()->update(['third_shift_amount' => 0, 'tax' => 0, 'advance' => 0]);

        // every column is rendered; the column picker marks empty ones and can hide them; Information & Basic are frozen
        $this->actingAs($this->admin)->get(route('payroll.show', $run))->assertOk()
            ->assertSee('data-c="third_shift_amount"', false)->assertSee('id="regFreeze"', false)
            ->assertSee('data-col-toggle="third_shift_amount"', false)->assertSee('· empty', false)
            ->assertSee('class="text-center g-info fz"', false);

        // Settings decides which columns are shown by default
        $this->actingAs($this->admin)->get(route('settings.edit'))->assertOk()->assertSee('Salary sheet columns')->assertSee('Food Coupon');
        $all = array_column(\App\Support\SalaryLayout::registerColumns(), 0);
        $this->actingAs($this->admin)->put(route('settings.register-columns'), ['visible' => array_diff($all, ['grade', 'food_coupon'])])->assertRedirect();
        $this->assertEqualsCanonicalizing(['grade', 'food_coupon'], \App\Support\SalaryLayout::hiddenByDefault());
        $this->actingAs($this->admin)->get(route('payroll.show', $run))->assertSee('data-default-hidden=\'["grade","food_coupon"]\'', false);

        $slip = $run->payslips()->first();
        $this->actingAs($this->admin)->get(route('payslips.show', $slip))->assertOk()->assertDontSee('ADVANCE');
    }
}
