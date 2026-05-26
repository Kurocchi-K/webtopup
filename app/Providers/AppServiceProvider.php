<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <--- INI HARUS DI SINI (DI LUAR CLASS)

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
        // Memaksa HTTPS agar Mixed Content hilang
        if (config('app.env') === 'production' || request()->secure()) {
            URL::forceScheme('https');
        }
    }
}
