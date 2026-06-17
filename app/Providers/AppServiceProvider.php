<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MudadApiClientInterface;
use App\Contracts\TwoFactorProviderInterface;
use App\Services\Auth\GoogleTwoFactorProvider;
use App\Services\Payroll\Saudi\MudadHttpClient;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mudad WPS — bind interface to the HTTP implementation.
        // Tests inject a Factory-faked MudadHttpClient directly (see MudadHttpClientTest).
        $this->app->singleton(MudadApiClientInterface::class, MudadHttpClient::class);

        // TOTP provider — swap in a test double via constructor injection in unit tests.
        $this->app->singleton(TwoFactorProviderInterface::class, GoogleTwoFactorProvider::class);
    }

    public function boot(): void {}
}
