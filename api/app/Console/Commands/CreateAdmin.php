<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Bootstraps an admin for the /admin panel (there is no registration or password reset page).
 *
 *   admin:create --from-env           ADMIN_NAME/ADMIN_EMAIL/ADMIN_PASSWORD; run by the entrypoint. Idempotent.
 *   admin:create --email=x            prompts for the password (hidden, never in argv or logs)
 *   admin:create --email=x --reset    sets a new password for an existing admin
 */
final class CreateAdmin extends Command
{
    private const int MIN_PASSWORD_LENGTH = 12;

    protected $signature = 'admin:create
        {--email= : Email of the admin}
        {--name= : Display name (defaults to the part before @)}
        {--from-env : Read ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD}
        {--reset : Set a new password for an existing admin}';

    protected $description = 'Create an admin user for the /admin panel (idempotent)';

    public function handle(): int
    {
        $fromEnv = (bool) $this->option('from-env');
        $email = Str::lower(trim((string) ($fromEnv ? config('admin.bootstrap.email') : $this->option('email'))));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error($fromEnv ? 'ADMIN_EMAIL is missing or not a valid email.' : 'Pass a valid --email.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        // Never overwrite an existing account (and never reset a password someone changed) unless --reset.
        if ($existing !== null && ! $this->option('reset')) {
            $this->info("Admin {$email} already exists; nothing to do.");

            return self::SUCCESS;
        }

        $password = $fromEnv
            ? (string) config('admin.bootstrap.password')
            : (string) $this->secret('Password (min '.self::MIN_PASSWORD_LENGTH.' characters)');

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->error(($fromEnv ? 'ADMIN_PASSWORD' : 'The password').' must be at least '.self::MIN_PASSWORD_LENGTH.' characters.');

            return self::FAILURE;
        }

        if ($existing !== null) {
            $existing->forceFill(['password' => $password, 'is_admin' => true])->save();
            $this->info("Password reset for admin {$email}.");

            return self::SUCCESS;
        }

        $name = trim((string) ($fromEnv ? config('admin.bootstrap.name') : $this->option('name')));

        User::query()->forceCreate([
            'name' => $name !== '' ? $name : Str::before($email, '@'),
            'email' => $email,
            'password' => $password, // hashed by the model's `hashed` cast
            'is_admin' => true,
        ]);

        $this->info("Admin {$email} created.");

        return self::SUCCESS;
    }
}
