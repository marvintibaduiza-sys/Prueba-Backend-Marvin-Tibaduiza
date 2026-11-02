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
        // ISO 4217 reserves codes starting with "X" for non-national units,
        // so generated codes never collide with real ones such as USD or EUR.
        $code = fake()->unique()->regexify('X[A-Z]{2}');

        return [
            'name' => "Test Currency {$code}",
            'code' => $code,
            'symbol' => fake()->randomElement(['¤', '₳', '₴', '₲']),
            'exchange_rate' => fake()->randomFloat(6, 0.1, 5000),
        ];
    }
}
