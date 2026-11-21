<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Product;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Shop-origin mutations need the XSRF token; other callers stay stateless with no CSRF. */
final class StatefulCsrfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel skips CSRF checks in tests; this subclass turns them back on for Sanctum's stateful requests.
        config(['sanctum.middleware.validate_csrf_token' => EnforcedCsrf::class]);
        Mail::fake();
    }

    /** @return array<string, mixed> */
    private function enquiry(): array
    {
        return ['product_id' => Product::factory()->create()->id, 'name' => 'Ada', 'email' => 'ada@example.com'];
    }

    public function test_csrf_cookie_endpoint_sets_the_xsrf_cookie_for_the_shop(): void
    {
        $this->get('/sanctum/csrf-cookie', self::SHOP)
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_shop_origin_post_without_a_token_is_419(): void
    {
        $this->postJson('/api/v1/enquiries', $this->enquiry(), self::SHOP)->assertStatus(419);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x'], self::SHOP)->assertStatus(419);
    }

    public function test_shop_origin_post_with_the_token_is_accepted(): void
    {
        $this->withSession(['_token' => 'test-token'])
            ->postJson('/api/v1/enquiries', $this->enquiry(), self::SHOP + ['X-CSRF-TOKEN' => 'test-token'])
            ->assertCreated();
    }

    public function test_requests_without_a_shop_origin_need_no_token(): void
    {
        $this->postJson('/api/v1/enquiries', $this->enquiry())->assertCreated();
    }
}

final class EnforcedCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
