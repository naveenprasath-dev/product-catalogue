<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** GET /auth/me and POST /auth/logout. */
final class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_logged_in_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->getJson('/api/v1/auth/me', self::SHOP)
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }

    public function test_me_for_a_guest_is_401_json(): void
    {
        $this->getJson('/api/v1/auth/me', self::SHOP)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_bearer_tokens_are_not_accepted(): void
    {
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer 1|abcdef'])->assertUnauthorized();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/auth/logout', [], self::SHOP)
            ->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_logout_as_a_guest_is_401(): void
    {
        $this->postJson('/api/v1/auth/logout', [], self::SHOP)->assertUnauthorized();
    }
}
