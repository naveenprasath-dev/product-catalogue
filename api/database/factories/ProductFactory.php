<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'source' => 'fakestore',
            'external_id' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 1, 500),
            'image_url' => fake()->imageUrl(),
            'rating_rate' => fake()->randomFloat(1, 0, 4.5),
            'rating_count' => fake()->numberBetween(0, 600),
            'imported_at' => now(),
        ];
    }

    public function forCategory(Category $category): self
    {
        return $this->state(['category_id' => $category->id]);
    }

    public function rated(float $rate): self
    {
        return $this->state(['rating_rate' => $rate]);
    }

    public function topRated(): self
    {
        return $this->state(['rating_rate' => 5.0]);
    }
}
