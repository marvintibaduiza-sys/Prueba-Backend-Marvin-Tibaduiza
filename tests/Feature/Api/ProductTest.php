<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/products';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    private function usd(): Currency
    {
        return Currency::where('name', 'USD')->firstOrFail();
    }

    private function eur(): Currency
    {
        return Currency::where('name', 'EUR')->firstOrFail();
    }

    /**
     * Valid data for the request. Pass null to drop a field.
     */
    private function payload(array $overrides = []): array
    {
        return array_filter(array_merge([
            'name' => 'Wireless Mouse',
            'description' => 'Ergonomic wireless mouse',
            'price' => 25.5,
            'tax_cost' => 4.85,
            'manufacturing_cost' => 10,
        ], $overrides), fn ($value) => $value !== null);
    }

    // ---------------------------------------------------------------- index

    public function test_admin_lists_products_paginated_with_their_currency(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(3)->for($this->usd())->create();

        $this->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.currency.name', 'USD')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']]);
    }

    public function test_index_caps_per_page_at_100(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI.'?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_seller_can_list_products(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI)->assertOk();
    }

    public function test_guest_cannot_list_products(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    // ----------------------------------------------------------------- show

    public function test_show_includes_currency_and_prices(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->for($this->usd())->create(['price' => 19.99]);
        ProductPrice::factory()->for($product)->for($this->eur())->create(['price' => 17.8]);

        $this->getJson(self::URI."/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.price', '19.99')
            ->assertJsonPath('data.currency.name', 'USD')
            ->assertJsonPath('data.prices.0.price', '17.80')
            ->assertJsonPath('data.prices.0.currency.name', 'EUR');
    }

    public function test_seller_can_show_a_product(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI.'/'.Product::factory()->create()->id)->assertOk();
    }

    public function test_show_unknown_or_non_numeric_id_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI.'/999')->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        $this->getJson(self::URI.'/abc')->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_guest_cannot_show_a_product(): void
    {
        $this->getJson(self::URI.'/'.Product::factory()->create()->id)->assertUnauthorized();
    }

    // ---------------------------------------------------------------- store

    public function test_admin_creates_a_product_in_usd_by_default(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::URI, $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Wireless Mouse')
            ->assertJsonPath('data.price', '25.50')
            ->assertJsonPath('data.tax_cost', '4.85')
            ->assertJsonPath('data.manufacturing_cost', '10.00')
            ->assertJsonPath('data.currency.name', 'USD');

        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'name' => 'Wireless Mouse',
            'currency_id' => $this->usd()->id,
        ]);
    }

    public function test_product_can_be_created_in_another_base_currency(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload(['currency_id' => $this->eur()->id]))
            ->assertCreated()
            ->assertJsonPath('data.currency.name', 'EUR');
    }

    public function test_seller_can_create_a_product(): void
    {
        $this->actingAsSeller();

        $this->postJson(self::URI, $this->payload())->assertCreated();
    }

    public function test_guest_cannot_create_a_product(): void
    {
        $this->postJson(self::URI, $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('products', 0);
    }

    public static function invalidStorePayloads(): array
    {
        return [
            'name with digits' => [['name' => 'Laptop 15'], 'name'],
            'name with symbols' => [['name' => 'Mouse@Pro'], 'name'],
            'name without letters' => [['name' => '- - -'], 'name'],
            'name with a line break' => [['name' => "Wireless\nMouse"], 'name'],
            'name with a tab' => [['name' => "Wireless\tMouse"], 'name'],
            'name missing' => [['name' => null], 'name'],
            'name blank' => [['name' => '   '], 'name'],
            'name too long' => [['name' => str_repeat('a', 151)], 'name'],
            'description missing' => [['description' => null], 'description'],
            'description too long' => [['description' => str_repeat('a', 1001)], 'description'],
            'price missing' => [['price' => null], 'price'],
            'price not numeric' => [['price' => 'abc'], 'price'],
            'price zero' => [['price' => 0], 'price'],
            'price negative' => [['price' => -5], 'price'],
            'price with three decimals' => [['price' => 1.999], 'price'],
            'price too large' => [['price' => 10000000000], 'price'],
            'tax cost missing' => [['tax_cost' => null], 'tax_cost'],
            'tax cost negative' => [['tax_cost' => -1], 'tax_cost'],
            'tax cost too large' => [['tax_cost' => 10000000000], 'tax_cost'],
            'manufacturing cost missing' => [['manufacturing_cost' => null], 'manufacturing_cost'],
            'manufacturing cost negative' => [['manufacturing_cost' => -1], 'manufacturing_cost'],
            'manufacturing cost with three decimals' => [['manufacturing_cost' => 2.555], 'manufacturing_cost'],
            'currency nonexistent' => [['currency_id' => 999], 'currency_id'],
            'currency not an integer' => [['currency_id' => 'abc'], 'currency_id'],
            'currency boolean' => [['currency_id' => true], 'currency_id'],
        ];
    }

    #[DataProvider('invalidStorePayloads')]
    public function test_store_validation(array $overrides, string $field): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('products', 0);
    }

    public static function acceptedNames(): array
    {
        return [
            'accents' => ['Café Molido'],
            'eñe' => ['Ñandú de Peluche'],
            'hyphen' => ['Teclado Inalámbrico-Pro'],
            'decomposed accent' => ["Cafe\u{0301} Molido"],
        ];
    }

    #[DataProvider('acceptedNames')]
    public function test_unicode_letters_and_hyphens_are_accepted_in_names(string $name): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload(['name' => $name]))
            ->assertCreated()
            ->assertJsonPath('data.name', $name);
    }

    public function test_explicit_null_currency_defaults_to_usd(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, [...$this->payload(), 'currency_id' => null])
            ->assertCreated()
            ->assertJsonPath('data.currency.name', 'USD');
    }

    public function test_missing_base_currency_gives_a_validation_error_not_a_404(): void
    {
        $this->actingAsAdmin();
        Currency::query()->delete();

        $this->postJson(self::URI, $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency_id');
    }

    public function test_zero_costs_are_accepted(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload(['tax_cost' => 0, 'manufacturing_cost' => 0]))
            ->assertCreated()
            ->assertJsonPath('data.tax_cost', '0.00');
    }

    public function test_store_rolls_back_and_hides_details_on_unexpected_error(): void
    {
        $this->actingAsAdmin();
        Product::creating(fn () => throw new RuntimeException('database exploded'));

        $response = $this->postJson(self::URI, $this->payload());

        $response->assertStatus(500)->assertExactJson(['message' => 'An unexpected error occurred.']);
        $this->assertDatabaseCount('products', 0);
    }

    // --------------------------------------------------------------- update

    public function test_admin_updates_a_product_partially(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->for($this->usd())->create(['name' => 'Old Name', 'price' => 10]);

        $this->putJson(self::URI."/{$product->id}", ['price' => 12.75])
            ->assertOk()
            ->assertJsonPath('data.price', '12.75')
            ->assertJsonPath('data.name', 'Old Name');

        $this->assertSame('12.75', $product->fresh()->price);
    }

    public function test_seller_can_update_a_product(): void
    {
        $this->actingAsSeller();
        $product = Product::factory()->create();

        $this->putJson(self::URI."/{$product->id}", ['name' => 'Renamed Product'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Product');
    }

    public function test_update_validates_sent_fields(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create(['name' => 'Valid Name']);

        $this->putJson(self::URI."/{$product->id}", ['name' => 'Laptop 15', 'price' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price']);

        $this->assertSame('Valid Name', $product->fresh()->name);
    }

    public function test_update_rejects_base_currency_that_already_has_a_price(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->for($this->usd())->create();
        ProductPrice::factory()->for($product)->for($this->eur())->create();

        $this->putJson(self::URI."/{$product->id}", ['currency_id' => $this->eur()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_id' => 'The product already has a price in this currency.']);
    }

    public function test_price_registered_concurrently_in_the_new_base_currency_returns_409(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->for($this->usd())->create();
        $eurId = $this->eur()->id;
        $retrievals = 0;

        // Another request adds an EUR price right after validation.
        Product::retrieved(function (Product $retrieved) use (&$retrievals, $eurId) {
            if (++$retrievals === 2) {
                DB::table('product_prices')->insert([
                    'product_id' => $retrieved->id, 'currency_id' => $eurId, 'price' => 9,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $this->putJson(self::URI."/{$product->id}", ['currency_id' => $eurId])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The product already has a price in this currency.']);

        $this->assertSame($this->usd()->id, $product->fresh()->currency_id);
    }

    public function test_update_unknown_product_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI.'/999', ['price' => 10])->assertNotFound();
    }

    public function test_guest_cannot_update_a_product(): void
    {
        $this->putJson(self::URI.'/'.Product::factory()->create()->id, ['price' => 10])->assertUnauthorized();
    }

    // -------------------------------------------------------------- destroy

    public function test_admin_deletes_only_the_target_product_and_its_prices(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->for($this->eur())->create();
        $other = Product::factory()->create();
        $otherPrice = ProductPrice::factory()->for($other)->for($this->eur())->create();

        $this->deleteJson(self::URI."/{$product->id}")->assertNoContent();

        $this->assertModelMissing($product);
        $this->assertDatabaseMissing('product_prices', ['product_id' => $product->id]);
        $this->assertModelExists($other);
        $this->assertModelExists($otherPrice);
    }

    public function test_seller_cannot_delete_a_product(): void
    {
        $this->actingAsSeller();
        $product = Product::factory()->create();

        $this->deleteJson(self::URI."/{$product->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);

        $this->assertModelExists($product);
    }

    public function test_delete_unknown_product_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson(self::URI.'/999')->assertNotFound();
    }

    public function test_guest_cannot_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson(self::URI."/{$product->id}")->assertUnauthorized();
        $this->assertModelExists($product);
    }
}
