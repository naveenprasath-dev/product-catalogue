<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategoryProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_rated_are_the_top_three_by_rating_and_others_the_rest(): void
    {
        $category = Category::factory()->create(['slug' => 'electronics', 'name' => 'electronics']);
        // Created out of order on purpose: the API must sort by rating.
        $rates = ['e' => 2.2, 'a' => 4.8, 'c' => 3.3, 'd' => 2.9, 'b' => 4.1];
        $ids = [];
        foreach ($rates as $key => $rate) {
            $ids[$key] = Product::factory()->forCategory($category)->rated($rate)->create()->id;
        }

        $this->getJson('/api/v1/categories/electronics/products')
            ->assertOk()
            ->assertJsonPath('data.category', ['slug' => 'electronics', 'name' => 'electronics'])
            ->assertJsonPath('data.top_rated.*.id', [$ids['a'], $ids['b'], $ids['c']])
            ->assertJsonPath('data.others.*.id', [$ids['d'], $ids['e']])
            ->assertJsonPath('data.top_rated.0.rating.rate', 4.8);
    }

    public function test_category_with_three_or_fewer_products_has_no_others(): void
    {
        $category = Category::factory()->create(['slug' => 'jewelery']);
        Product::factory()->count(3)->forCategory($category)->create();

        $this->getJson('/api/v1/categories/jewelery/products')
            ->assertOk()
            ->assertJsonCount(3, 'data.top_rated')
            ->assertJsonCount(0, 'data.others');
    }

    public function test_products_from_other_categories_never_appear(): void
    {
        $category = Category::factory()->create(['slug' => 'jewelery']);
        $other = Category::factory()->create(['slug' => 'electronics']);
        $mine = Product::factory()->count(4)->forCategory($category)->create();
        Product::factory()->count(3)->forCategory($other)->topRated()->create();

        $response = $this->getJson('/api/v1/categories/jewelery/products')->assertOk();

        $returned = array_merge($response->json('data.top_rated.*.id'), $response->json('data.others.*.id'));
        sort($returned);
        $this->assertSame($mine->pluck('id')->sort()->values()->all(), $returned);
    }

    public function test_unknown_slug_returns_404_json(): void
    {
        $this->getJson('/api/v1/categories/does-not-exist/products')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);
    }
}
