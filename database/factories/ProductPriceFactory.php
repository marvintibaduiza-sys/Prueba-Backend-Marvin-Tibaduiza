<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'currency_id' => Currency::factory(),
            'price' => fake()->randomFloat(2, 1, 1000),
        ];
    }
}
