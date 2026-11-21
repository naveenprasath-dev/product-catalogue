<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Session/cookie auth is only for the shop origin; every other API caller stays cookie-free. */
final class StatelessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_request_without_a_shop_origin_sets_no_cookie(): void
    {
        Product::factory()->for(Category::factory())->create();

        $this->assertNoCookies($this->getJson('/api/v1/products?search=a')->assertOk());
        $this->assertNoCookies($this->getJson('/api/v1/categories')->assertOk());
        $this->assertNoCookies($this->getJson('/api/v1/nope')->assertNotFound());
    }

    public function test_request_from_another_origin_stays_stateless(): void
    {
        $this->assertNoCookies($this->getJson('/api/v1/categories', ['Origin' => 'https://evil.example'])->assertOk());
    }

    public function test_request_from_the_shop_origin_gets_a_session_cookie(): void
    {
        $response = $this->getJson('/api/v1/categories', ['Origin' => 'http://localhost:5173'])->assertOk();

        $this->assertNotEmpty($response->headers->getCookies());
    }

    private function assertNoCookies(TestResponse $response): void
    {
        $this->assertSame([], $response->headers->getCookies());
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }
}
