<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\AuthService;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shop and admin share the session ("web") guard.
        $this->app->bind(AuthService::class, function (Container $app): AuthService {
            $guard = $app->make(AuthManager::class)->guard('web');
            assert($guard instanceof StatefulGuard);

            return new AuthService($guard);
        });
    }

    public function boot(): void
    {
        // Cookie (SPA) auth only: ignore bearer tokens, so there is no personal_access_tokens table to query.
        Sanctum::getAccessTokenFromRequestUsing(fn (): ?string => null);

        // POST /enquiries: a few per minute per client IP is plenty for a human.
        RateLimiter::for('enquiries', fn (Request $request): Limit => Limit::perMinute((int) config('catalogue.enquiry_rate_limit'))
            ->by((string) $request->ip()));

        // Shop login: 5 attempts a minute per email + IP (same rule as the admin login).
        RateLimiter::for('shop-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::transliterate(Str::lower($request->string('email')->trim()->toString())).'|'.$request->ip()));

        // Shop sign-up: 3 a minute per IP.
        RateLimiter::for('shop-register', fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->ip()));
    }
}
