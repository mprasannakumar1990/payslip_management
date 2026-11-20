<?php

use Illuminate\Support\Facades\Schedule;

// Daily database backup. On Windows / XAMPP this is triggered by the "Payslip DB Backup" task in Task Scheduler
// (see README); on a Linux server add the usual cron entry for `php artisan schedule:run`.
Schedule::command('backup:database --if-due')->dailyAt('23:00')->withoutOverlapping();
