<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class CreateAdminCommandTest extends AdminTestCase
{
    private function bootstrapConfig(?string $email, ?string $password, string $name = 'Shop Owner'): void
    {
        config(['admin.bootstrap' => ['name' => $name, 'email' => $email, 'password' => $password]]);
    }

    public function test_it_creates_an_admin_from_env(): void
    {
        $this->bootstrapConfig('Owner@Example.com', 'a-long-local-password');

        $this->artisan('admin:create', ['--from-env' => true])
            ->expectsOutputToContain('owner@example.com created')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertSame('Shop Owner', $user->name);
        $this->assertTrue(Hash::check('a-long-local-password', $user->password));
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->bootstrapConfig('owner@example.com', 'a-long-local-password');
        $this->artisan('admin:create', ['--from-env' => true])->assertExitCode(0);

        $this->bootstrapConfig('owner@example.com', 'another-long-password');
        $this->artisan('admin:create', ['--from-env' => true])
            ->expectsOutputToContain('already exists')
            ->assertExitCode(0);

        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('a-long-local-password', (string) User::query()->value('password')));
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->bootstrapConfig('owner@example.com', 'short');

        $this->artisan('admin:create', ['--from-env' => true])
            ->expectsOutputToContain('at least 12 characters')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_a_missing_email_is_refused(): void
    {
        $this->bootstrapConfig(null, 'a-long-local-password');

        $this->artisan('admin:create', ['--from-env' => true])
            ->expectsOutputToContain('ADMIN_EMAIL is missing')
            ->assertExitCode(1);
    }

    public function test_interactive_create_and_reset_prompt_for_a_hidden_password(): void
    {
        $this->artisan('admin:create', ['--email' => 'staff@example.com'])
            ->expectsQuestion('Password (min 12 characters)', 'first-long-password')
            ->assertExitCode(0);

        $this->artisan('admin:create', ['--email' => 'staff@example.com', '--reset' => true])
            ->expectsQuestion('Password (min 12 characters)', 'second-long-password')
            ->expectsOutputToContain('Password reset')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'staff@example.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertSame('staff', $user->name);
        $this->assertTrue(Hash::check('second-long-password', $user->password));
    }
}
