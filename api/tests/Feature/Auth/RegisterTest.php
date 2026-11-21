<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'Ada@Example.com',
            'password' => 'analytical-engine-1',
            'password_confirmation' => 'analytical-engine-1',
        ], $overrides);
    }

    public function test_register_creates_a_customer_and_logs_them_in(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(), self::SHOP)
            ->assertCreated()
            ->assertExactJson(['data' => ['id' => User::query()->value('id'), 'name' => 'Ada Lovelace', 'email' => 'ada@example.com']]);

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_admin);
        $this->assertNotSame('analytical-engine-1', $user->getAuthPassword());
    }

    public function test_is_admin_in_the_body_is_ignored(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['is_admin' => true]), self::SHOP)->assertCreated();

        $this->assertFalse(User::query()->sole()->is_admin);
    }

    public function test_duplicate_email_and_weak_or_unconfirmed_password_are_rejected(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'short', 'password_confirmation' => 'other']), self::SHOP)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/register', $this->payload(['email' => "user{$i}@example.com"]), self::SHOP)->assertCreated();
        }

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'user9@example.com']), self::SHOP)
            ->assertTooManyRequests()
            ->assertJsonStructure(['message']);
    }

    public function test_register_without_a_shop_session_is_forbidden(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertForbidden()->assertJsonStructure(['message']);

        $this->assertDatabaseCount('users', 0);
    }
}
