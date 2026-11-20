<?php

namespace App\Http\Middleware;

use App\Services\BackupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Safety net for the daily backup: if the scheduled task did not run (PC was off, MySQL stopped …),
 * the first signed-in page view after 24 hours triggers a backup after the response has been sent.
 */
class EnsureDailyBackup
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (! $request->user() || app()->runningUnitTests() || $request->expectsJson() || $request->prefetch()) {
            return;
        }
        try {
            $backup = app(BackupService::class);
            // at most one automatic attempt per hour, so a failing backup never slows every page
            if ($backup->isDue() && Cache::add('backup-auto-attempt', 1, 3600)) {
                $backup->run('automatic');
            }
        } catch (\Throwable) {
        }
    }
}
