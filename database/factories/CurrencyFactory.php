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
            // Names start with X so they never match real ones like USD.
            'name' => fake()->unique()->regexify('X[A-Z]{2}'),
            'symbol' => fake()->randomElement(['¤', '₳', '₴', '₲']),
            'exchange_rate' => fake()->randomFloat(6, 0.1, 5000),
        ];
    }
}
