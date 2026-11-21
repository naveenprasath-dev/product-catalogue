<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
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
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]));

        // Only admins may log in here. A customer (or a wrong password) gets the same generic error.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', Str::lower((string) $request->input(Fortify::username())))->first();

            if ($user === null || ! $user->is_admin || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
