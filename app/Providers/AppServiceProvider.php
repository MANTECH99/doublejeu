<?php

namespace App\Providers;

use Cose\Algorithm\ManagerFactory;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES256K;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\EdDSA;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
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

        $this->app->bind(ManagerFactory::class, function (): ManagerFactory {
            $factory = new ManagerFactory;

            $factory->add((string) RS256::identifier(), new RS256);
            $factory->add((string) RS384::identifier(), new RS384);
            $factory->add((string) RS512::identifier(), new RS512);
            $factory->add((string) PS256::identifier(), new PS256);
            $factory->add((string) PS384::identifier(), new PS384);
            $factory->add((string) PS512::identifier(), new PS512);
            $factory->add((string) ES256::identifier(), new ES256);
            $factory->add((string) ES256K::identifier(), new ES256K);
            $factory->add((string) ES384::identifier(), new ES384);
            $factory->add((string) ES512::identifier(), new ES512);
            $factory->add((string) EdDSA::identifier(), new EdDSA);

            return $factory;
        });
    }
}
