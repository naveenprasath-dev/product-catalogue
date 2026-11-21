<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_id_returns_product_details(): void
    {
        $category = Category::factory()->create(['slug' => 'jewelery', 'name' => 'jewelery']);
        $product = Product::factory()->forCategory($category)->create([
            'title' => 'Silver Ring',
            'description' => 'A ring.',
            'price' => 10.99,
            'image_url' => 'https://example.test/ring.png',
            'rating_rate' => 4.6,
            'rating_count' => 400,
        ]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $product->id,
                'title' => 'Silver Ring',
                'description' => 'A ring.',
                'price' => 10.99,
                'image_url' => 'https://example.test/ring.png',
                'category' => ['slug' => 'jewelery', 'name' => 'jewelery'],
                'rating' => ['rate' => 4.6, 'count' => 400],
            ]]);
    }

    public function test_unknown_id_returns_404_json(): void
    {
        $this->getJson('/api/v1/products/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);
    }

    public function test_non_numeric_or_oversized_id_returns_404_without_trace(): void
    {
        foreach (['abc', '1;drop', '99999999999999999999999'] as $id) {
            $this->getJson("/api/v1/products/{$id}")
                ->assertNotFound()
                ->assertExactJson(['message' => 'Not found.']);
        }
    }
}
