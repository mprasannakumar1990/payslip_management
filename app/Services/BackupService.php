<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
                $this->zip($path, $sql, $base.'.sql', $db['database'], $trigger);
            } finally {
                @unlink($sql);
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

    /** Package the dump as a ZIP with a short README on how to restore it. */
    private function zip(string $zipPath, string $sqlFile, string $sqlName, string $database, string $trigger): void
    {
        $zip = new \ZipArchive();
        $tmp = $zipPath.'.part';
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the ZIP file in '.dirname($zipPath));
        }
        $zip->addFile($sqlFile, $sqlName);
        $zip->setCompressionName($sqlName, \ZipArchive::CM_DEFLATE, 9);
        $zip->addFromString('README.txt', implode("\r\n", [
            setting('company_name').' - Payslip & HR Suite',
            'Database backup',
            '',
            'Database : '.$database,
            'Created  : '.now()->format('d M Y h:i:s A'),
            'Type     : '.$trigger,
            'File     : '.$sqlName.' (full MySQL dump: tables + data)',
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
            ->map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'at' => Carbon::createFromTimestamp(filemtime($f))])
            ->sortByDesc('at')->values()->all();
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
