<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private Product $backpack;

    private Product $ring;

    private Product $drive;

    protected function setUp(): void
    {
        parent::setUp();

        $clothing = Category::factory()->create(['slug' => 'mens-clothing', 'name' => "men's clothing"]);
        $jewelery = Category::factory()->create(['slug' => 'jewelery', 'name' => 'jewelery']);
        $electronics = Category::factory()->create(['slug' => 'electronics', 'name' => 'electronics']);

        $this->backpack = Product::factory()->forCategory($clothing)->create([
            'title' => 'Fjallraven Foldsack Backpack', 'description' => 'Fits 15 inch laptops.',
        ]);
        $this->ring = Product::factory()->forCategory($jewelery)->create([
            'title' => 'Solid Gold Petite Micropave', 'description' => 'Satisfaction guaranteed.',
        ]);
        $this->drive = Product::factory()->forCategory($electronics)->create([
            'title' => 'WD 2TB Elements', 'description' => 'USB 3.0 portable hard drive.',
        ]);
    }

    public function test_matches_title(): void
    {
        $this->getJson('/api/v1/products?search=backpack')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->backpack->id]);
    }

    public function test_matches_description(): void
    {
        $this->getJson('/api/v1/products?search=hard%20drive')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->drive->id]);
    }

    public function test_matches_category_name(): void
    {
        $this->getJson('/api/v1/products?search=jewel')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->ring->id]);
    }

    public function test_is_case_insensitive(): void
    {
        $this->getJson('/api/v1/products?search=GOLD')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->ring->id]);
    }

    public function test_like_wildcards_in_the_term_are_matched_literally(): void
    {
        $this->getJson('/api/v1/products?search=%25')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_no_matches_returns_empty_list(): void
    {
        $this->getJson('/api/v1/products?search=submarine')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_empty_term_is_rejected(): void
    {
        $this->getJson('/api/v1/products?search=')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);

        $this->getJson('/api/v1/products?search=%20%20')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    public function test_missing_term_is_rejected(): void
    {
        $this->getJson('/api/v1/products')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }

    public function test_term_longer_than_100_characters_is_rejected(): void
    {
        $this->getJson('/api/v1/products?search='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }
}
