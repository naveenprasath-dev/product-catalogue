<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Enquiry> */
final class EnquiryFactory extends Factory
{
    protected $model = Enquiry::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => null,
            'product_title' => fn (array $attributes): string => (string) Product::query()->whereKey($attributes['product_id'])->value('title'),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->numerify('+44 7### ######'),
            'message' => fake()->optional()->sentence(12),
            'status' => EnquiryStatus::Sent,
            'mailed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => EnquiryStatus::Pending, 'mailed_at' => null]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => EnquiryStatus::Failed, 'mailed_at' => null]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
