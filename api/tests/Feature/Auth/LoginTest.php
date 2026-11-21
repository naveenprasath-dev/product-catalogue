<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_log_in_and_return_the_user_without_secrets(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ADA@example.com', 'password' => UserFactory::PASSWORD], self::SHOP)
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => 'ada@example.com']]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_gives_a_generic_422(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'nope'], self::SHOP)
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'These credentials do not match our records.',
                'errors' => ['email' => ['These credentials do not match our records.']],
            ]);

        $this->assertGuest();
    }

    public function test_unknown_email_gives_the_same_error(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever'], self::SHOP)
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    public function test_sixth_attempt_in_a_minute_is_throttled(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'nope'], self::SHOP)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => UserFactory::PASSWORD], self::SHOP)
            ->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_admins_can_also_log_in_to_the_shop(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORD], self::SHOP)
            ->assertOk()
            ->assertJsonMissingPath('data.is_admin');
    }

    public function test_login_without_a_shop_session_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])->assertForbidden();
        $this->assertGuest();
    }
}
