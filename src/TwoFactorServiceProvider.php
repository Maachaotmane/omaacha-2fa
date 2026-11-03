<?php

namespace Omaacha\TwoFactor;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Omaacha\TwoFactor\Channels\OtpChannelManager;
use Omaacha\TwoFactor\Http\Middleware\EnsureTwoFactorConfirmed;
use Omaacha\TwoFactor\Http\Middleware\EnsureTwoFactorEnabled;
use Omaacha\TwoFactor\Managers\AuthChallengeManager;
use Omaacha\TwoFactor\Managers\EmailOtpManager;
use Omaacha\TwoFactor\Managers\RecoveryCodeManager;
use Omaacha\TwoFactor\Managers\TotpManager;
use Omaacha\TwoFactor\Managers\TrustedDeviceManager;
use Omaacha\TwoFactor\Services\TwoFactorService;

class TwoFactorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/two-factor.php', 'two-factor');

        $this->app->singleton(OtpChannelManager::class, function ($app) {
            return new OtpChannelManager($app);
        });

        $this->app->singleton(TotpManager::class, function () {
            return new TotpManager();
        });

        $this->app->singleton(RecoveryCodeManager::class, function () {
            return new RecoveryCodeManager();
        });

        $this->app->singleton(TrustedDeviceManager::class, function () {
            return new TrustedDeviceManager();
        });

        $this->app->singleton(AuthChallengeManager::class, function () {
            return new AuthChallengeManager();
        });

        $this->app->singleton(EmailOtpManager::class, function ($app) {
            return new EmailOtpManager(
                $app->make(OtpChannelManager::class)
            );
        });

        $this->app->singleton(TwoFactorService::class, function ($app) {
            $tokenIssuer = $app->bound(\Omaacha\TwoFactor\Contracts\TwoFactorTokenIssuer::class)
                ? $app->make(\Omaacha\TwoFactor\Contracts\TwoFactorTokenIssuer::class)
                : null;

            return new TwoFactorService(
                $app->make(TotpManager::class),
                $app->make(TrustedDeviceManager::class),
                $app->make(EmailOtpManager::class),
                $app->make(RecoveryCodeManager::class),
                $app->make(AuthChallengeManager::class),
                $app->make(OtpChannelManager::class),
                $tokenIssuer
            );
        });
    }

    public function boot(): void
    {
        // 1. Publishing configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/two-factor.php' => config_path('two-factor.php'),
            ], 'two-factor-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'two-factor-migrations');

            $this->publishes([
                __DIR__ . '/../lang' => $this->app->langPath('vendor/two-factor'),
            ], 'two-factor-translations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/two-factor'),
            ], 'two-factor-views');
        }

        // 2. Load translations & views
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'two-factor');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'two-factor');

        // 3. Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // 4. Register middleware aliases
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('2fa.sudo', EnsureTwoFactorConfirmed::class);
        $router->aliasMiddleware('2fa.enforce', EnsureTwoFactorEnabled::class);
    }
}

