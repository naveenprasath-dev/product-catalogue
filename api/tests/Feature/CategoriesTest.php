<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_all_categories_with_slug_and_name_sorted_by_name(): void
    {
        Category::factory()->create(['slug' => 'womens-clothing', 'name' => "women's clothing"]);
        Category::factory()->create(['slug' => 'electronics', 'name' => 'electronics']);
        Category::factory()->create(['slug' => 'jewelery', 'name' => 'jewelery']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['slug' => 'electronics', 'name' => 'electronics'],
                ['slug' => 'jewelery', 'name' => 'jewelery'],
                ['slug' => 'womens-clothing', 'name' => "women's clothing"],
            ]]);
    }

    public function test_empty_database_returns_empty_list(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
