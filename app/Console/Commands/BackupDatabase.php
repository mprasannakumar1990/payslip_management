<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--if-due : Only back up when the last successful backup is older than 24 hours}';

    protected $description = 'Back up the MySQL database to storage/app/backups (gzip) and remove old backups';

    public function handle(BackupService $backup): int
    {
        if ($this->option('if-due') && ! $backup->isDue()) {
            $this->info('A backup was already made in the last 24 hours ('.$backup->lastBackupAt()?->format('d M Y H:i').'). Nothing to do.');

            return self::SUCCESS;
        }
        $this->info('Backing up the database…');
        $r = $backup->run('scheduled');
        if (! $r['ok']) {
            $this->error('Backup failed: '.$r['error']);

            return self::FAILURE;
        }
        $this->info('Backup saved: '.$r['file'].' ('.BackupService::size($r['size']).')'.($r['copied'] ? " — copied to {$r['copied']}" : ''));

        return self::SUCCESS;
    }
}
