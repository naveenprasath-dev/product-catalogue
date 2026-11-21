<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Codes are random three-letter strings starting with X (ISO 4217 keeps X.. for non-national use),
 * so they never clash with the USD/EUR/GBP/INR rows the migration inserts.
 *
 * @extends Factory<Currency>
 */
final class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => 'X'.fake()->unique()->regexify('[A-Z]{2}'),
            'name' => ucfirst(fake()->word()).' Dollar',
            'symbol' => fake()->randomElement(['¤', 'X$', 'Fr']),
            'rate' => fake()->randomFloat(4, 0.1, 200),
            'enabled' => true,
            'is_default' => false,
            'rate_updated_at' => null,
        ];
    }

    public function disabled(): self
    {
        return $this->state(['enabled' => false]);
    }
}
