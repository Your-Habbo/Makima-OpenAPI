<?php

namespace App\Providers;

use App\Services\TwoFactorService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TwoFactorService::class);
    }

    public function boot(): void
    {
        // Ensure Spatie Permission uses the correct guard
        config(['permission.defaults.guard' => 'web']);
    }
}