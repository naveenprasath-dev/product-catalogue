<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;

/**
 * Shop customer sign-up, login and logout on the shared session ("web") guard.
 * The controller owns the session itself (regenerate / invalidate); this class owns the user rules.
 */
final readonly class AuthService
{
    public function __construct(private StatefulGuard $guard) {}

    /** Creates a customer (never an admin: is_admin is not fillable) and logs them in. */
    public function register(string $name, string $email, string $password): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password, // hashed by the model cast
        ]);

        $this->guard->login($user);

        return $user;
    }

    /** Null when the email/password pair is wrong (the caller gives one generic error). */
    public function login(string $email, string $password, bool $remember): ?User
    {
        if (! $this->guard->attempt(['email' => $email, 'password' => $password], $remember)) {
            return null;
        }

        $user = $this->guard->user();

        return $user instanceof User ? $user : null;
    }

    public function logout(): void
    {
        $this->guard->logout();
    }
}
