<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FeaturedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_exactly_the_configured_products_in_configured_order(): void
    {
        $category = Category::factory()->create(['slug' => 'mens-clothing', 'name' => "men's clothing"]);
        foreach (['1', '2', '3', '4', '5', '6', '7'] as $externalId) {
            Product::factory()->forCategory($category)->create(['external_id' => $externalId]);
        }
        config(['catalogue.featured_ids' => ['5', '1', '7', '3', '2']]);

        $response = $this->getJson('/api/v1/products/featured')->assertOk();

        $titles = Product::query()->pluck('title', 'external_id');
        $this->assertSame(
            [$titles['5'], $titles['1'], $titles['7'], $titles['3'], $titles['2']],
            array_column($response->json('data'), 'title'),
        );
    }

    public function test_response_matches_the_product_contract_without_raw_columns(): void
    {
        $category = Category::factory()->create(['slug' => 'mens-clothing', 'name' => "men's clothing"]);
        $product = Product::factory()->forCategory($category)->create([
            'external_id' => '1',
            'price' => 109.95,
            'rating_rate' => 3.9,
            'rating_count' => 120,
        ]);
        config(['catalogue.featured_ids' => ['1']]);

        $this->getJson('/api/v1/products/featured')
            ->assertOk()
            ->assertExactJsonStructure(['data' => [['id', 'title', 'description', 'price', 'image_url',
                'category' => ['slug', 'name'], 'rating' => ['rate', 'count']]]])
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.price', 109.95)
            ->assertJsonPath('data.0.rating.rate', 3.9)
            ->assertJsonPath('data.0.rating.count', 120)
            ->assertJsonPath('data.0.category', ['slug' => 'mens-clothing', 'name' => "men's clothing"])
            ->assertJsonMissingPath('data.0.external_id')
            ->assertJsonMissingPath('data.0.source');
    }

    public function test_configured_id_missing_from_database_is_skipped(): void
    {
        Product::factory()->create(['external_id' => '1']);
        Product::factory()->create(['external_id' => '2']);
        config(['catalogue.featured_ids' => ['1', '999', '2']]);

        $this->getJson('/api/v1/products/featured')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
