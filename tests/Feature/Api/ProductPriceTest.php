<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductPriceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->product = Product::factory()->for($this->currency('USD'))->create();
    }

    private function currency(string $name): Currency
    {
        return Currency::where('name', $name)->firstOrFail();
    }

    private function uri(?Product $product = null): string
    {
        return '/api/products/'.($product ?? $this->product)->id.'/prices';
    }

    /**
     * A valid store payload; a null override removes that field.
     */
    private function payload(array $overrides = []): array
    {
        return array_filter(
            array_merge(['currency_id' => $this->currency('EUR')->id, 'price' => 22.7], $overrides),
            fn ($value) => $value !== null
        );
    }

    // ---------------------------------------------------------------- index

    public function test_index_lists_only_the_prices_of_the_given_product(): void
    {
        $this->actingAsAdmin();
        ProductPrice::factory()->for($this->product)->for($this->currency('EUR'))->create(['price' => 9.5]);
        ProductPrice::factory()->for(Product::factory())->for($this->currency('EUR'))->create();

        $this->getJson($this->uri())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $this->product->id)
            ->assertJsonPath('data.0.price', '9.50')
            ->assertJsonPath('data.0.currency.name', 'EUR')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_seller_can_list_prices(): void
    {
        $this->actingAsSeller();

        $this->getJson($this->uri())->assertOk();
    }

    public function test_guest_cannot_list_prices(): void
    {
        $this->getJson($this->uri())->assertUnauthorized();
    }

    public function test_listing_prices_of_unknown_product_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/products/999/prices')->assertNotFound();
    }

    // ---------------------------------------------------------------- store

    public function test_admin_registers_a_price_in_another_currency(): void
    {
        $this->actingAsAdmin();

        $this->postJson($this->uri(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.product_id', $this->product->id)
            ->assertJsonPath('data.price', '22.70')
            ->assertJsonPath('data.currency.name', 'EUR');

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $this->product->id,
            'currency_id' => $this->currency('EUR')->id,
        ]);
    }

    public function test_seller_can_register_a_price(): void
    {
        $this->actingAsSeller();

        $this->postJson($this->uri(), $this->payload())->assertCreated();
    }

    public function test_guest_cannot_register_a_price(): void
    {
        $this->postJson($this->uri(), $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_registering_a_price_for_unknown_product_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/products/999/prices', $this->payload())->assertNotFound();
    }

    public static function invalidPayloads(): array
    {
        return [
            'currency missing' => [['currency_id' => null], 'currency_id'],
            'currency nonexistent' => [['currency_id' => 999], 'currency_id'],
            'currency not an integer' => [['currency_id' => 'abc'], 'currency_id'],
            'currency boolean' => [['currency_id' => true], 'currency_id'],
            'price missing' => [['price' => null], 'price'],
            'price not numeric' => [['price' => 'abc'], 'price'],
            'price zero' => [['price' => 0], 'price'],
            'price negative' => [['price' => -3], 'price'],
            'price with three decimals' => [['price' => 1.999], 'price'],
            'price too large' => [['price' => 10000000000], 'price'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_store_validation(array $overrides, string $field): void
    {
        $this->actingAsAdmin();

        $this->postJson($this->uri(), $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_price_cannot_use_the_product_base_currency(): void
    {
        $this->actingAsAdmin();

        $this->postJson($this->uri(), $this->payload(['currency_id' => $this->currency('USD')->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_id' => 'The price currency must differ from the product base currency.']);
    }

    public function test_product_cannot_have_two_prices_in_the_same_currency(): void
    {
        $this->actingAsAdmin();
        ProductPrice::factory()->for($this->product)->for($this->currency('EUR'))->create();

        $this->postJson($this->uri(), $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_id' => 'The product already has a price in this currency.']);

        $this->assertDatabaseCount('product_prices', 1);
    }

    public function test_same_currency_is_allowed_for_different_products(): void
    {
        $this->actingAsAdmin();
        ProductPrice::factory()->for(Product::factory())->for($this->currency('EUR'))->create();

        $this->postJson($this->uri(), $this->payload())->assertCreated();
    }

    public function test_base_currency_changed_concurrently_to_the_price_currency_returns_409(): void
    {
        $this->actingAsAdmin();
        $eurId = $this->currency('EUR')->id;
        $retrievals = 0;

        // Right after route binding loads the product (still USD), another request makes EUR its
        // base currency; validation passes on the stale model, the locked re-check must catch it.
        Product::retrieved(function (Product $product) use (&$retrievals, $eurId) {
            if (++$retrievals === 1) {
                DB::table('products')->where('id', $product->id)->update(['currency_id' => $eurId]);
            }
        });

        $this->postJson($this->uri(), $this->payload())
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The price currency must differ from the product base currency.']);

        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_concurrent_duplicate_is_caught_by_the_database_and_returns_409(): void
    {
        $this->actingAsAdmin();
        $eurId = $this->currency('EUR')->id;

        // Simulates another request inserting the same price after validation passed.
        ProductPrice::creating(function (ProductPrice $price) use ($eurId) {
            DB::table('product_prices')->insert([
                'product_id' => $price->product_id,
                'currency_id' => $eurId,
                'price' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson($this->uri(), $this->payload())
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The product already has a price in this currency.']);

        $this->assertDatabaseCount('product_prices', 0);
    }
}
