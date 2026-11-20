<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\BackupService;
use Illuminate\Http\Request;

/** Super Admin: database backups. */
class BackupController extends Controller
{
    public function __construct(private BackupService $backup)
    {
    }

    public function index()
    {
        return view('backups.index', [
            'files' => $this->backup->list(),
            'last' => $this->backup->lastBackupAt(),
            'status' => Setting::get('backup_last_status'),
            'error' => Setting::get('backup_last_error'),
            'failedAt' => Setting::get('backup_failed_at'),
            'dir' => $this->backup->dir(),
            'task' => $this->taskStatus(),
            's' => Setting::allValues(),
        ]);
    }

    /** "Back up now" (manual button or the daily reminder). With download=1 the ZIP is downloaded right after. */
    public function store(Request $request)
    {
        $trigger = $request->input('trigger') === 'reminder' ? 'reminder' : 'manual';
        $r = $this->backup->run($trigger);
        if (! $r['ok']) {
            return back()->with('error', 'Backup failed: '.$r['error']);
        }
        $name = basename($r['file']);
        $res = back()->with('success', 'Backup created: '.$name.' ('.BackupService::size($r['size']).')'.($r['copied'] ? " and copied to {$r['copied']}" : '').'.'
            .($request->boolean('download') ? ' The ZIP download starts automatically.' : ''));

        return $request->boolean('download') ? $res->with('backup_download', route('backups.download', $name)) : $res;
    }

    public function download(string $name)
    {
        $path = $this->backup->path($name);
        abort_unless($path, 404);
        ActivityLog::record('backup', "Downloaded backup $name");

        return response()->download($path, $name, ['Content-Type' => str_ends_with($name, '.zip') ? 'application/zip' : 'application/gzip']);
    }

    public function destroy(string $name)
    {
        $path = $this->backup->path($name);
        abort_unless($path, 404);
        @unlink($path);
        ActivityLog::record('backup', "Deleted backup $name");

        return back()->with('success', "Backup $name deleted.");
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'backup_keep_days' => ['required', 'integer', 'between:1,3650'],
            'backup_copy_path' => ['nullable', 'string', 'max:255'],
            'backup_reminder_time' => ['required', 'date_format:H:i'],
        ], ['backup_reminder_time.date_format' => 'Choose a valid reminder time (HH:MM).']);
        $copy = trim((string) ($data['backup_copy_path'] ?? ''));
        if ($copy !== '') {
            try {
                \Illuminate\Support\Facades\File::ensureDirectoryExists($copy);
                if (! is_writable($copy)) {
                    throw new \RuntimeException('not writable');
                }
            } catch (\Throwable) {
                return back()->withInput()->withErrors(['backup_copy_path' => "The folder \"$copy\" does not exist and could not be created, or is not writable."]);
            }
        }
        $remind = $request->boolean('backup_reminder_enabled');
        Setting::put(['backup_keep_days' => (string) $data['backup_keep_days'], 'backup_copy_path' => $copy,
            'backup_reminder_enabled' => $remind ? '1' : '0', 'backup_reminder_time' => $data['backup_reminder_time']]);
        ActivityLog::record('backup', "Updated backup settings (keep {$data['backup_keep_days']} days, reminder "
            .($remind ? 'daily at '.$data['backup_reminder_time'] : 'off').($copy ? ", copy to $copy" : '').')');

        return back()->with('success', 'Backup settings saved.');
    }

    /** Is the Windows scheduled task installed? (informational only) */
    private function taskStatus(): ?array
    {
        if (PHP_OS_FAMILY !== 'Windows' || ! function_exists('exec')) {
            return null;
        }
        $out = [];
        @exec('schtasks /Query /TN "Payslip DB Backup" /FO LIST /V 2>NUL', $out, $code);
        if ($code !== 0) {
            return ['installed' => false];
        }
        $info = [];
        foreach ($out as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                $info[$k] = $v;
            }
        }

        return ['installed' => true, 'next' => $info['Next Run Time'] ?? null, 'last' => $info['Last Run Time'] ?? null, 'result' => $info['Last Result'] ?? null, 'status' => $info['Status'] ?? null];
    }
}
