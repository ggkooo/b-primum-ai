<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow long-running AI requests without PHP-side execution cutoff.
        @set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('default_socket_timeout', '0');
    }
}
