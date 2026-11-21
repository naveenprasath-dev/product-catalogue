<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** ?currency= on every product endpoint: conversion, rounding, default, and 422s. */
final class ProductCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::factory()->create(['slug' => 'electronics', 'name' => 'electronics']);
        $this->product = Product::factory()->forCategory($category)->topRated()
            ->create(['external_id' => '1', 'title' => 'Backpack', 'price' => 109.95]);
        config(['catalogue.featured_ids' => ['1']]);
        Currency::query()->whereKey('EUR')->update(['rate' => '0.91003']);
    }

    /** @return array<string, array{0: string, 1: string}> url, JSON path of the product */
    public static function endpoints(): array
    {
        return [
            'featured' => ['/api/v1/products/featured', 'data.0'],
            'search' => ['/api/v1/products?search=backpack', 'data.0'],
            'show' => ['/api/v1/products/{id}', 'data'],
            'category' => ['/api/v1/categories/electronics/products', 'data.top_rated.0'],
        ];
    }

    private function url(string $url, string $query = ''): string
    {
        $url = str_replace('{id}', (string) $this->product->id, $url);

        return $query === '' ? $url : $url.(str_contains($url, '?') ? '&' : '?').$query;
    }

    #[DataProvider('endpoints')]
    public function test_without_currency_prices_are_in_the_default_currency_unchanged(string $url, string $path): void
    {
        $this->getJson($this->url($url))
            ->assertOk()
            ->assertJsonPath("{$path}.price", 109.95)
            ->assertJsonPath("{$path}.currency", 'USD');
    }

    #[DataProvider('endpoints')]
    public function test_converts_to_the_requested_currency_case_insensitively(string $url, string $path): void
    {
        // 109.95 × 0.91003 = 100.0577985 → 100.06
        $this->getJson($this->url($url, 'currency=eur'))
            ->assertOk()
            ->assertJsonPath("{$path}.price", 100.06)
            ->assertJsonPath("{$path}.currency", 'EUR');
    }

    #[DataProvider('endpoints')]
    public function test_unknown_currency_is_a_422(string $url, string $path): void
    {
        $this->getJson($this->url($url, 'currency=XYZ'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency'])
            ->assertJsonStructure(['message', 'errors' => ['currency']]);
    }

    #[DataProvider('endpoints')]
    public function test_disabled_currency_is_a_422(string $url, string $path): void
    {
        Currency::query()->whereKey('GBP')->update(['enabled' => false]);

        $this->getJson($this->url($url, 'currency=GBP'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    #[DataProvider('endpoints')]
    public function test_malformed_currency_is_a_422(string $url, string $path): void
    {
        foreach (['currency=EURO', 'currency=E1', 'currency[]=EUR'] as $query) {
            $this->getJson($this->url($url, $query))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['currency']);
        }
    }

    public function test_the_admin_chosen_default_applies_when_currency_is_absent(): void
    {
        Currency::query()->whereKey('USD')->update(['is_default' => false]);
        Currency::query()->whereKey('INR')->update(['is_default' => true, 'rate' => '92.93']);

        // 109.95 × 92.93 = 10217.6535 → 10217.65
        $this->getJson('/api/v1/products/'.$this->product->id)
            ->assertOk()
            ->assertJsonPath('data.price', 10217.65)
            ->assertJsonPath('data.currency', 'INR');
    }

    public function test_rounds_half_up_to_two_decimals(): void
    {
        $this->product->update(['price' => 1.0]);
        Currency::query()->whereKey('GBP')->update(['rate' => '0.765']);   // 0.765 exactly half → 0.77

        $this->getJson('/api/v1/products/'.$this->product->id.'?currency=GBP')
            ->assertOk()
            ->assertJsonPath('data.price', 0.77);
    }

    public function test_every_product_in_a_category_listing_uses_the_same_currency(): void
    {
        Product::factory()->count(4)->forCategory(Category::query()->firstOrFail())->create();

        $data = $this->getJson('/api/v1/categories/electronics/products?currency=INR')->assertOk()->json('data');

        $currencies = array_unique(array_column([...$data['top_rated'], ...$data['others']], 'currency'));
        $this->assertSame(['INR'], array_values($currencies));
    }

    public function test_404_and_search_validation_still_work_alongside_currency(): void
    {
        $this->getJson('/api/v1/products/999999?currency=EUR')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->getJson('/api/v1/categories/nope/products?currency=EUR')->assertNotFound();
        $this->getJson('/api/v1/products?currency=EUR')->assertStatus(422)->assertJsonValidationErrors(['search']);
    }
}
