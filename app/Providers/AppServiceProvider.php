<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        $host = (string) request()->header('host', '');

        if ($scheme === 'https' || str_contains($host, 'billeteriexpress.com')) {
            URL::forceScheme('https');
        }
    }
}
