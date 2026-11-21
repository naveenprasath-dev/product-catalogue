<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** GET /api/v1/currencies. The migration seeds USD (base, default), EUR, GBP and INR. */
final class CurrenciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_enabled_currencies_default_first_then_by_code(): void
    {
        Currency::factory()->create(['code' => 'XAA']);
        Currency::factory()->disabled()->create(['code' => 'XAB']);

        $response = $this->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertExactJsonStructure([
                'data' => ['*' => ['code', 'name', 'symbol', 'rate', 'is_default']],
                'meta' => ['base', 'rates_updated_at'],
            ])
            ->assertJsonPath('meta.base', 'USD')
            ->assertJsonPath('meta.rates_updated_at', null);

        $this->assertSame(['USD', 'EUR', 'GBP', 'INR', 'XAA'], array_column($response->json('data'), 'code'));
        $response->assertJsonPath('data.0', ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 1, 'is_default' => true]);
    }

    public function test_a_non_base_default_comes_first_and_rates_are_numbers(): void
    {
        Currency::query()->whereKey('USD')->update(['is_default' => false]);
        Currency::query()->whereKey('GBP')->update(['is_default' => true, 'rate' => '0.76131']);

        $response = $this->getJson('/api/v1/currencies')->assertOk();

        $this->assertSame(['GBP', 'EUR', 'INR', 'USD'], array_column($response->json('data'), 'code'));
        $response->assertJsonPath('data.0.rate', 0.76131)->assertJsonPath('data.0.is_default', true);
    }

    public function test_rates_updated_at_is_the_latest_rate_change(): void
    {
        Currency::query()->whereKey('EUR')->update(['rate_updated_at' => '2026-11-20 10:00:00+00']);
        Currency::query()->whereKey('INR')->update(['rate_updated_at' => '2026-11-21 09:30:00+00']);

        $this->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertJsonPath('meta.rates_updated_at', '2026-11-21T09:30:00+00:00');
    }

    public function test_shop_requests_never_call_the_rate_source(): void
    {
        Http::fake();
        $product = Product::factory()->create();

        $this->getJson('/api/v1/currencies')->assertOk();
        $this->getJson("/api/v1/products/{$product->id}?currency=EUR")->assertOk();

        Http::assertNothingSent();
    }
}
