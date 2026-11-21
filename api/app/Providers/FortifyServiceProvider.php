<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Responses\AdminLogoutResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

/**
 * Admin login/logout only (from the starter kit, trimmed). config/fortify.php has no features, so
 * there is no registration, password reset, email verification, 2FA or passkeys.
 */
final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // routes/web.php registers only the login/logout routes we use (Fortify would also add
        // password-confirmation routes), and none at all when the admin panel is disabled.
        Fortify::ignoreRoutes();

        // Clears the browser's Inertia history on logout (QA B3).
        $this->app->singleton(LogoutResponse::class, AdminLogoutResponse::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]));

        // Only admins may log in here. A customer (or a wrong password) gets the same generic error.
        // Every attempt runs exactly one password hash check (against a dummy hash for unknown emails and
        // customers), so the response time doesn't reveal which emails are admin accounts (QA B1).
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', Str::lower((string) $request->input(Fortify::username())))->first();
            $isAdmin = $user !== null && $user->is_admin;

            $passwordMatches = Hash::check(
                (string) $request->input('password'),
                $isAdmin ? $user->password : self::dummyHash(),
            );

            return $isAdmin && $passwordMatches ? $user : null;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * A hash of a random password with the current cost, made once and cached (making it per request
     * would itself add a hash's worth of time to failed attempts). Never matches a real password.
     */
    private static function dummyHash(): string
    {
        $rounds = (int) config('hashing.bcrypt.rounds', 12);

        return (string) Cache::rememberForever("admin-login-dummy-hash:{$rounds}", fn (): string => Hash::make(Str::random(40)));
    }
}
