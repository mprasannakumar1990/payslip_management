<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Daily MySQL backups: mysqldump → gzip → storage/app/backups (+ optional copy to another folder),
 * old files removed after the retention period. Status is kept in settings for the dashboard.
 */
class BackupService
{
    public const PREFIX = 'db-backup-';

    public function dir(): string
    {
        $dir = storage_path('app'.DIRECTORY_SEPARATOR.'backups');
        File::ensureDirectoryExists($dir);
        if (! is_file($dir.'/.htaccess')) {
            File::put($dir.'/.htaccess', "Require all denied\n");
        }

        return $dir;
    }

    public function mysqldump(): string
    {
        $configured = env('MYSQLDUMP_PATH');
        if ($configured) {
            return $configured;
        }
        foreach (['C:\\xampp\\mysql\\bin\\mysqldump.exe', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return 'mysqldump';
    }

    /**
     * Create a backup. Returns ['ok' => bool, 'file' => path|null, 'size' => bytes, 'error' => string|null, 'copied' => path|null].
     */
    public function run(string $trigger = 'manual'): array
    {
        $lock = Cache::lock('db-backup-running', 600);
        if (! $lock->get()) {
            return ['ok' => false, 'file' => null, 'size' => 0, 'copied' => null, 'error' => 'Another backup is already running.'];
        }
        @set_time_limit(0);
        try {
            $db = config('database.connections.'.config('database.default'));
            if (($db['driver'] ?? '') !== 'mysql' && ($db['driver'] ?? '') !== 'mariadb') {
                throw new \RuntimeException('Backups are only supported for MySQL / MariaDB databases.');
            }
            $stamp = now()->format('Y-m-d_His');
            $base = self::PREFIX.$db['database'].'-'.$stamp;
            $name = $base.'.zip';
            $path = $this->dir().DIRECTORY_SEPARATOR.$name;
            $sql = $this->dir().DIRECTORY_SEPARATOR.$base.'.sql.part';

            $process = new Process([
                $this->mysqldump(),
                '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
                '--single-transaction', '--quick', '--routines', '--triggers', '--events',
                '--default-character-set=utf8mb4', '--add-drop-table', '--hex-blob',
                // full structure + all data; one INSERT per row with column names so the data is readable
                '--skip-extended-insert', '--complete-insert',
                $db['database'],
            ], null, $this->processEnv((string) $db['password']), null, 600);

            $fh = fopen($sql, 'wb');
            $errors = '';
            $process->run(function ($type, $buffer) use ($fh, &$errors) {
                if ($type === Process::OUT) {
                    fwrite($fh, $buffer);
                } else {
                    $errors .= $buffer;
                }
            });
            fclose($fh);

            try {
                if (! $process->isSuccessful()) {
                    throw new \RuntimeException(trim($errors) ?: 'mysqldump exited with code '.$process->getExitCode());
                }
                // sanity check: a real dump always ends with this line
                if (! $this->looksComplete($sql)) {
                    throw new \RuntimeException('The backup is incomplete. '.trim($errors));
                }
                // Excel copy of all data (one sheet per table). A problem here never stops the SQL backup.
                $xlsx = $this->dir().DIRECTORY_SEPARATOR.$base.'.xlsx.part';
                try {
                    $this->excel($xlsx);
                } catch (\Throwable $e) {
                    @unlink($xlsx);
                    $errors .= ' Excel copy skipped: '.$e->getMessage();
                }
                $this->zip($path, $sql, $base.'.sql', $db['database'], $trigger, is_file($xlsx) ? $xlsx : null, $base.'.xlsx');
            } finally {
                @unlink($sql);
                @unlink($xlsx ?? '');
            }
            $size = filesize($path);

            $copied = null;
            $copyDir = trim((string) Setting::get('backup_copy_path', ''));
            if ($copyDir !== '') {
                try {
                    File::ensureDirectoryExists($copyDir);
                    File::copy($path, rtrim($copyDir, '\\/').DIRECTORY_SEPARATOR.$name);
                    $copied = $copyDir;
                    $this->prune($copyDir);
                } catch (\Throwable $e) {
                    $errors .= ' Copy to "'.$copyDir.'" failed: '.$e->getMessage();
                }
            }
            $removed = $this->prune($this->dir());

            Setting::put(['backup_last_at' => now()->toDateTimeString(), 'backup_last_status' => 'ok',
                'backup_last_error' => $copied === null && $copyDir !== '' ? trim($errors) : '', 'backup_last_file' => $name]
                + (in_array($trigger, ['manual', 'reminder'], true) ? ['backup_last_manual_at' => now()->toDateTimeString()] : []));
            $this->log("Database backup created ($trigger): $name (".self::size($size).')'.($removed ? ", $removed old backup(s) removed" : '').($copied ? ", copied to $copied" : ''));

            return ['ok' => true, 'file' => $path, 'size' => $size, 'copied' => $copied, 'error' => null];
        } catch (\Throwable $e) {
            Setting::put(['backup_last_status' => 'failed', 'backup_last_error' => mb_substr($e->getMessage(), 0, 500), 'backup_failed_at' => now()->toDateTimeString()]);
            $this->log("Database backup FAILED ($trigger): ".mb_substr($e->getMessage(), 0, 180));

            return ['ok' => false, 'file' => null, 'size' => 0, 'copied' => null, 'error' => $e->getMessage()];
        } finally {
            $lock->release();
        }
    }

    /**
     * Environment for mysqldump. On Windows the socket library fails (error 10106) when SystemRoot is missing,
     * which happens when PHP runs under a web server that passes a trimmed environment.
     */
    private function processEnv(string $password): array
    {
        $env = ['MYSQL_PWD' => $password];
        if (PHP_OS_FAMILY === 'Windows') {
            $root = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: getenv('windir') ?: 'C:\\Windows';
            $env += ['SystemRoot' => $root, 'SYSTEMROOT' => $root, 'windir' => $root,
                'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(), 'TMP' => getenv('TMP') ?: sys_get_temp_dir()];
        }

        return $env;
    }

    private function looksComplete(string $sqlFile): bool
    {
        $size = filesize($sqlFile);
        if (! $size) {
            return false;
        }
        $fh = fopen($sqlFile, 'rb');
        fseek($fh, max(0, $size - 4096));
        $tail = (string) fread($fh, 4096);
        fclose($fh);

        return str_contains($tail, '-- Dump completed');
    }

    /** Tables left out of the Excel copy: framework internals with no business data. */
    public const EXCEL_SKIP = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations'];

    /** Columns never written to Excel in readable form. */
    private const EXCEL_MASK = ['password', 'remember_token'];

    /** Readable sheet names for the main tables. */
    private const EXCEL_TITLES = [
        'employees' => 'Employees', 'employee_allowances' => 'Employee Allowances', 'allowance_types' => 'Allowance Types',
        'leave_types' => 'Leave Types', 'leave_allocations' => 'Leave Allocations', 'leave_requests' => 'Leave Requests',
        'leave_adjustments' => 'Monthly Leave', 'el_ledgers' => 'EL Ledger', 'ot_entries' => 'OT Entries',
        'payroll_runs' => 'Payroll Runs', 'payslips' => 'Payslips', 'settlements' => 'Settlements',
        'settings' => 'Settings', 'users' => 'Admin Users', 'activity_logs' => 'Activity Log',
    ];

    /**
     * Write every business table (structure = column headings, all rows) to an .xlsx workbook, one sheet per table,
     * plus a "Summary" sheet listing the tables and row counts.
     */
    public function excel(string $path): void
    {
        $schema = DB::getDriverName() === 'sqlite' ? null : DB::getDatabaseName();
        $tables = collect(Schema::getTableListing($schema, false))->reject(fn ($t) => in_array($t, self::EXCEL_SKIP, true))
            ->sortBy(fn ($t) => [array_search($t, array_keys(self::EXCEL_TITLES), true) === false ? 1 : 0, array_search($t, array_keys(self::EXCEL_TITLES), true), $t])
            ->values();
        $book = new \App\Support\XlsxBuilder();
        $summary = [];
        $sheets = [];
        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $rows = DB::table($table)->orderBy($columns[0] ?? 'id')->get()->map(function ($r) use ($columns) {
                $out = [];
                foreach ($columns as $c) {
                    $v = $r->$c ?? null;
                    $secret = in_array($c, self::EXCEL_MASK, true) || ($c === 'value' && isset($r->key) && str_contains((string) $r->key, 'password'));
                    if ($secret && $v !== null && $v !== '') {
                        $v = '(hidden)';
                    } elseif (is_string($v) && preg_match('/^-?(0|[1-9]\d{0,14})(\.\d+)?$/', $v)) {
                        $v = str_contains($v, '.') ? (float) $v : (int) $v; // numbers as numbers; IDs with leading zeros stay text
                    }
                    $out[] = $v;
                }

                return $out;
            });
            $title = self::EXCEL_TITLES[$table] ?? $table;
            $sheets[] = [$title, $columns, $rows];
            $summary[] = [$title, $table, count($columns), $rows->count()];
        }
        $book->sheet('Summary', ['Sheet', 'Database table', 'Columns', 'Rows'], array_merge(
            [['Backup of '.setting('company_name').' — '.now()->format('d M Y h:i A').' IST', '', '', '']],
            $summary,
            [['Total', '', array_sum(array_column($summary, 2)), array_sum(array_column($summary, 3))]]
        ), ['highlight' => ['/^Backup of/' => 'E0E7FF', '/^Total$/' => 'F1F5F9']]);
        foreach ($sheets as [$title, $columns, $rows]) {
            $book->sheet($title, $columns, $rows, ['autofilter' => true]);
        }
        $book->save($path);
    }

    /** Package the dump (and the Excel copy) as a ZIP with a short README on how to restore it. */
    private function zip(string $zipPath, string $sqlFile, string $sqlName, string $database, string $trigger, ?string $xlsxFile = null, ?string $xlsxName = null): void
    {
        $zip = new \ZipArchive();
        $tmp = $zipPath.'.part';
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the ZIP file in '.dirname($zipPath));
        }
        $zip->addFile($sqlFile, $sqlName);
        $zip->setCompressionName($sqlName, \ZipArchive::CM_DEFLATE, 9);
        if ($xlsxFile) {
            $zip->addFile($xlsxFile, $xlsxName);
        }
        $zip->addFromString('README.txt', implode("\r\n", [
            setting('company_name').' - Payslip & HR Suite',
            'Database backup',
            '',
            'Database : '.$database,
            'Created  : '.now()->format('d M Y h:i:s A'),
            'Type     : '.$trigger,
            'File     : '.$sqlName.' (full MySQL dump: all table structures + all data, one row per INSERT)',
            $xlsxFile ? 'Excel    : '.$xlsxName.' (all data, one sheet per table, for viewing only; restore from the .sql file)' : 'Excel    : not included',
            '',
            'HOW TO RESTORE (this replaces ALL current data):',
            '1. In the app, take a fresh backup first (Database Backups -> Back up now).',
            '2. Extract '.$sqlName.' from this ZIP.',
            '3. Open a command prompt and run:',
            '   C:\\xampp\\mysql\\bin\\mysql -u root -e "DROP DATABASE '.$database.'; CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"',
            '   C:\\xampp\\mysql\\bin\\mysql -u root '.$database.' < '.$sqlName,
            '4. In the project folder run: php artisan cache:clear',
        ]));
        if (! $zip->close()) {
            @unlink($tmp);
            throw new \RuntimeException('Could not write the ZIP file.');
        }
        rename($tmp, $zipPath);
    }

    /** Backup files in a folder (ZIP packages and the older .sql.gz files). */
    private function files(string $dir): array
    {
        $dir = rtrim($dir, '\\/').DIRECTORY_SEPARATOR.self::PREFIX;

        return array_merge(glob($dir.'*.zip') ?: [], glob($dir.'*.sql.gz') ?: []);
    }

    /** Delete backups older than the retention period (always keeps the newest 3). */
    public function prune(string $dir): int
    {
        $keepDays = max(1, (int) Setting::get('backup_keep_days', 30));
        $files = collect($this->files($dir))->sortByDesc(fn ($f) => filemtime($f))->values();
        $removed = 0;
        foreach ($files->slice(3) as $f) {
            if (filemtime($f) < now()->subDays($keepDays)->timestamp) {
                @unlink($f);
                $removed++;
            }
        }

        return $removed;
    }

    public function list(): array
    {
        return collect($this->files($this->dir()))
            ->map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'at' => Carbon::createFromTimestamp(filemtime($f)),
                'sql' => (bool) $this->entry($f, 'sql'), 'xlsx' => (bool) $this->entry($f, 'xlsx')])
            ->sortByDesc('at')->values()->all();
    }

    /** Name of the .sql / .xlsx file inside a backup ZIP, or null (older .sql.gz backups have no ZIP entries). */
    public function entry(string $zipPath, string $ext): ?string
    {
        if (! str_ends_with($zipPath, '.zip')) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            return null;
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (str_ends_with(strtolower($n), '.'.$ext)) {
                    return $n;
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    /** Copy one file out of a backup ZIP to a temporary path (for downloading the .sql or .xlsx on its own). */
    public function extract(string $zipPath, string $ext): ?array
    {
        $entry = $this->entry($zipPath, $ext);
        if (! $entry) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bk');
        $in = fopen('zip://'.$zipPath.'#'.$entry, 'rb');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return [$tmp, $entry];
    }

    public function path(string $name): ?string
    {
        if (! preg_match('/^'.preg_quote(self::PREFIX, '/').'[A-Za-z0-9_\-]+\.(zip|sql\.gz)$/', $name)) {
            return null;
        }
        $p = $this->dir().DIRECTORY_SEPARATOR.$name;

        return is_file($p) ? $p : null;
    }

    public function lastBackupAt(): ?Carbon
    {
        $v = Setting::get('backup_last_at');

        return $v ? Carbon::parse($v) : null;
    }

    /** True when no successful backup in the last 24 hours. */
    public function isDue(): bool
    {
        $last = $this->lastBackupAt();

        return ! $last || $last->lt(now()->subHours(24));
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 2).' MB' : round($bytes / 1024, 1).' KB';
    }

    private function log(string $message): void
    {
        try {
            ActivityLog::create(['user_id' => auth()->id(), 'action' => 'backup', 'description' => mb_substr($message, 0, 250), 'ip_address' => app()->runningInConsole() ? 'scheduler' : request()?->ip()]);
        } catch (\Throwable) {
        }
    }
}
