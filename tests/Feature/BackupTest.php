<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_manage_backups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get('/backups')->assertForbidden();
        $this->actingAs($admin)->post('/backups')->assertForbidden();
        $this->actingAs($super)->get('/backups')->assertOk()->assertSee('Database Backups');
    }

    public function test_excel_copy_has_every_table_with_data_and_hides_secrets(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        Setting::put(['mail_password' => 'encrypted-secret']);
        $xlsx = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bk-'.uniqid().'.xlsx';
        app(BackupService::class)->excel($xlsx);

        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx);
        $names = $book->getSheetNames();
        $this->assertSame('Summary', $names[0]);
        $this->assertContains('Employees', $names);
        $this->assertContains('Payslips', $names);
        $this->assertNotContains('sessions', $names);
        $emp = $book->getSheetByName('Employees');
        $this->assertSame('id', $emp->getCell('A1')->getValue());
        $this->assertSame(\App\Models\Employee::count() + 1, $emp->getHighestDataRow()); // heading + every employee
        $users = $book->getSheetByName('Admin Users')->toArray();
        $pw = array_search('password', $users[0], true);
        $this->assertSame('(hidden)', $users[1][$pw]);
        $settings = collect($book->getSheetByName('Settings')->toArray());
        $this->assertSame('(hidden)', $settings->first(fn ($r) => ($r[1] ?? null) === 'mail_password')[2]);
        @unlink($xlsx);
    }

    public function test_sql_and_excel_can_be_downloaded_from_the_zip(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $svc = app(BackupService::class);
        $name = BackupService::PREFIX.'test-2026-11-24_100000.zip';
        $zip = new \ZipArchive();
        $zip->open($svc->dir().DIRECTORY_SEPARATOR.$name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('dump.sql', '-- sql dump');
        $zip->addFromString('dump.xlsx', 'xlsx-bytes');
        $zip->close();

        $this->actingAs($super)->get(route('backups.index'))->assertSee('format=xlsx', false)->assertSee('format=sql', false);
        $r = $this->actingAs($super)->get(route('backups.download', [$name, 'format' => 'xlsx']))->assertOk();
        $this->assertStringContainsString('dump.xlsx', $r->headers->get('content-disposition'));
        $this->actingAs($super)->get(route('backups.download', [$name, 'format' => 'sql']))->assertOk();
        $this->actingAs($super)->get(route('backups.download', $name))->assertOk();
        @unlink($svc->dir().DIRECTORY_SEPARATOR.$name);
    }

    public function test_download_rejects_unsafe_names(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->get('/backups/..%2F..%2F.env/download')->assertNotFound();
        $this->actingAs($super)->get('/backups/db-backup-missing.sql.gz/download')->assertNotFound();
    }

    public function test_old_backups_are_pruned_but_newest_three_kept(): void
    {
        Setting::put(['backup_keep_days' => '30']);
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bk-test-'.uniqid();
        mkdir($dir);
        foreach ([1, 2, 40, 50, 60] as $daysOld) {
            $f = $dir.DIRECTORY_SEPARATOR.BackupService::PREFIX."x-$daysOld.sql.gz";
            file_put_contents($f, 'x');
            touch($f, now()->subDays($daysOld)->timestamp);
        }
        $removed = app(BackupService::class)->prune($dir);
        $left = array_map('basename', glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz'));
        $this->assertSame(2, $removed); // 50 and 60 days old; the 40-day-old one is among the newest 3
        $this->assertCount(3, $left);
        array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*'));
        rmdir($dir);
    }

    public function test_failure_is_recorded_and_due_logic(): void
    {
        $svc = app(BackupService::class);
        $this->assertTrue($svc->isDue());
        $r = $svc->run('test'); // tests use SQLite, which cannot be dumped
        $this->assertFalse($r['ok']);
        $this->assertSame('failed', Setting::get('backup_last_status'));

        Setting::put(['backup_last_at' => now()->subHours(2)->toDateTimeString()]);
        $this->assertFalse($svc->isDue());
        Setting::put(['backup_last_at' => now()->subHours(25)->toDateTimeString()]);
        $this->assertTrue($svc->isDue());
    }

    public function test_reminder_time_can_be_changed_and_is_validated(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->put('/backups/settings', ['backup_keep_days' => 30, 'backup_reminder_enabled' => 1, 'backup_reminder_time' => '17:30'])
            ->assertSessionHasNoErrors();
        $this->assertSame('17:30', Setting::get('backup_reminder_time'));
        $this->assertSame('1', Setting::get('backup_reminder_enabled'));

        $this->actingAs($super)->put('/backups/settings', ['backup_keep_days' => 30, 'backup_reminder_enabled' => 0, 'backup_reminder_time' => '25:99'])
            ->assertSessionHasErrors('backup_reminder_time');
        $this->assertSame('17:30', Setting::get('backup_reminder_time'));

        $this->actingAs($super)->put('/backups/settings', ['backup_keep_days' => 30, 'backup_reminder_enabled' => 0, 'backup_reminder_time' => '16:00']);
        $this->assertSame('0', Setting::get('backup_reminder_enabled'));
    }

    public function test_backup_button_and_reminder_only_for_super_admin(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($super)->get('/employees')->assertSee('data-backup-now', false)->assertSee('id="backupModal"', false);
        $this->actingAs($admin)->get('/employees')->assertDontSee('data-backup-now', false)->assertDontSee('id="backupModal"', false);
    }

    public function test_dashboard_warns_super_admin_when_backup_overdue(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->get('/')->assertSee('Database backup needs attention');
        Setting::put(['backup_last_at' => now()->toDateTimeString(), 'backup_last_status' => 'ok']);
        $this->actingAs($super)->get('/')->assertDontSee('Database backup needs attention');
    }
}
