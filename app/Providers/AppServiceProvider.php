<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Application services are resolved through constructor injection.
    }

    public function boot(): void
    {
        // No global bootstrapping is required.
    }
}
