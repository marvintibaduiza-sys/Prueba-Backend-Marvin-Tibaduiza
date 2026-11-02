<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            // ISO 4217 reserves identifiers starting with "X" for non-national units,
            // so generated names never collide with real ones such as USD or EUR.
            'name' => fake()->unique()->regexify('X[A-Z]{2}'),
            'symbol' => fake()->randomElement(['¤', '₳', '₴', '₲']),
            'exchange_rate' => fake()->randomFloat(6, 0.1, 5000),
        ];
    }
}
