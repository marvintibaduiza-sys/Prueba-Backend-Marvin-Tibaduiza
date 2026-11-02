<?php

namespace Tests\Unit\Models;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_belongs_to_its_base_currency(): void
    {
        $currency = Currency::factory()->create();
        $product = Product::factory()->for($currency)->create();

        $this->assertTrue($product->currency->is($currency));
    }

    public function test_product_has_many_prices(): void
    {
        $product = Product::factory()->create();
        ProductPrice::factory()->count(2)->for($product)->create();

        $this->assertCount(2, $product->prices);
    }

    public function test_product_price_belongs_to_product(): void
    {
        $price = ProductPrice::factory()->create();

        $this->assertInstanceOf(Product::class, $price->product);
    }

    public function test_product_price_belongs_to_currency(): void
    {
        $currency = Currency::factory()->create();
        $price = ProductPrice::factory()->for($currency)->create();

        $this->assertTrue($price->currency->is($currency));
    }

    public function test_currency_has_many_products(): void
    {
        $currency = Currency::factory()->create();
        Product::factory()->count(3)->for($currency)->create();

        $this->assertCount(3, $currency->products);
    }

    public function test_currency_has_many_prices(): void
    {
        $currency = Currency::factory()->create();
        ProductPrice::factory()->count(2)->for($currency)->create();

        $this->assertCount(2, $currency->prices);
    }

    public function test_deleting_product_cascades_its_prices(): void
    {
        $product = Product::factory()->create();
        ProductPrice::factory()->count(2)->for($product)->create();
        $otherPrice = ProductPrice::factory()->create();

        $product->delete();

        $this->assertDatabaseCount('product_prices', 1);
        $this->assertModelExists($otherPrice);
    }

    public function test_database_refuses_to_delete_a_currency_used_by_a_product(): void
    {
        $currency = Currency::factory()->create();
        Product::factory()->for($currency)->create();

        $this->expectException(QueryException::class);

        $currency->delete();
    }

    public function test_unused_currency_is_not_in_use(): void
    {
        $this->assertFalse(Currency::factory()->create()->isInUse());
    }

    public function test_currency_used_as_product_base_is_in_use(): void
    {
        $currency = Currency::factory()->create();
        Product::factory()->for($currency)->create();

        $this->assertTrue($currency->isInUse());
    }

    public function test_currency_used_in_a_product_price_is_in_use(): void
    {
        $currency = Currency::factory()->create();
        ProductPrice::factory()->for($currency)->create();

        $this->assertTrue($currency->isInUse());
    }

    public function test_exchange_rate_round_trips_as_exact_string(): void
    {
        $currency = Currency::factory()->create(['exchange_rate' => 0.89]);

        $this->assertSame('0.890000', $currency->fresh()->exchange_rate);
    }

    public function test_money_attributes_are_two_decimal_strings(): void
    {
        $product = Product::factory()->create(['price' => 19.9, 'tax_cost' => 2, 'manufacturing_cost' => 7.5]);
        $price = ProductPrice::factory()->create(['price' => 17.1]);

        $product->refresh();
        $this->assertSame('19.90', $product->price);
        $this->assertSame('2.00', $product->tax_cost);
        $this->assertSame('7.50', $product->manufacturing_cost);
        $this->assertSame('17.10', $price->fresh()->price);
    }

    public function test_base_returns_the_configured_base_currency(): void
    {
        $usd = Currency::factory()->create(['name' => 'USD']);
        Currency::factory()->create(['name' => 'EUR']);

        $this->assertTrue(Currency::base()->is($usd));
        $this->assertTrue($usd->isBase());
        $this->assertFalse(Currency::where('name', 'EUR')->first()->isBase());
    }
}
