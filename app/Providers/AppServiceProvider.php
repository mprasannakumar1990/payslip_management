<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\LeaveService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // SMTP settings edited in the app override .env (ignored before the database is migrated).
        try {
            Setting::applyMailConfig();
        } catch (\Throwable) {
        }
    }
}
