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

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/currencies';

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
     * A valid store payload; a null override removes that field.
     */
    private function payload(array $overrides = []): array
    {
        return array_filter(
            array_merge(['name' => 'GBP', 'symbol' => '£', 'exchange_rate' => 0.79], $overrides),
            fn ($value) => $value !== null
        );
    }

    // ---------------------------------------------------------------- index

    public function test_admin_lists_currencies_paginated(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI)
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'symbol', 'exchange_rate']], 'links', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_seller_can_list_currencies(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI)->assertOk();
    }

    public function test_guest_cannot_list_currencies(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    // ----------------------------------------------------------------- show

    public function test_admin_shows_a_currency(): void
    {
        $this->actingAsAdmin();
        $eur = $this->eur();

        $this->getJson(self::URI."/{$eur->id}")
            ->assertOk()
            ->assertJsonPath('data', [
                'id' => $eur->id,
                'name' => 'EUR',
                'symbol' => '€',
                'exchange_rate' => '0.890000',
                'created_at' => $eur->created_at->toIso8601String(),
                'updated_at' => $eur->updated_at->toIso8601String(),
            ]);
    }

    public function test_seller_can_show_a_currency(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI."/{$this->eur()->id}")->assertOk();
    }

    public function test_show_unknown_currency_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI.'/999')->assertNotFound();
    }

    public function test_guest_cannot_show_a_currency(): void
    {
        $this->getJson(self::URI."/{$this->eur()->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------- store

    public function test_admin_creates_a_currency(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'GBP')
            ->assertJsonPath('data.exchange_rate', '0.790000');

        $this->assertDatabaseHas('currencies', ['name' => 'GBP', 'symbol' => '£']);
    }

    public function test_seller_cannot_create_a_currency(): void
    {
        $this->actingAsSeller();

        $this->postJson(self::URI, $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('currencies', ['name' => 'GBP']);
    }

    public function test_guest_cannot_create_a_currency(): void
    {
        $this->postJson(self::URI, $this->payload())->assertUnauthorized();
    }

    public static function invalidStorePayloads(): array
    {
        return [
            'name missing' => [['name' => null], 'name'],
            'name too long' => [['name' => str_repeat('a', 51)], 'name'],
            'name duplicated' => [['name' => 'EUR'], 'name'],
            'symbol missing' => [['symbol' => null], 'symbol'],
            'symbol too long' => [['symbol' => 'ABCDEF'], 'symbol'],
            'rate missing' => [['exchange_rate' => null], 'exchange_rate'],
            'rate not numeric' => [['exchange_rate' => 'abc'], 'exchange_rate'],
            'rate zero' => [['exchange_rate' => 0], 'exchange_rate'],
            'rate negative' => [['exchange_rate' => -1], 'exchange_rate'],
            'rate with seven decimals' => [['exchange_rate' => 0.1234567], 'exchange_rate'],
            'rate too large' => [['exchange_rate' => 1000000], 'exchange_rate'],
        ];
    }

    #[DataProvider('invalidStorePayloads')]
    public function test_store_validation(array $overrides, string $field): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('currencies', 2);
    }

    public function test_store_rolls_back_and_hides_details_on_unexpected_error(): void
    {
        $this->actingAsAdmin();
        Currency::creating(fn () => throw new RuntimeException('database exploded'));

        $response = $this->postJson(self::URI, $this->payload());

        $response->assertStatus(500)->assertExactJson(['message' => 'An unexpected error occurred.']);
        $this->assertDatabaseMissing('currencies', ['name' => 'GBP']);
    }

    public function test_concurrent_duplicate_currency_returns_409_and_rolls_back(): void
    {
        $this->actingAsAdmin();

        // Simulates another request inserting the same name after validation passed.
        Currency::creating(fn (Currency $currency) => DB::table('currencies')->insert([
            'name' => $currency->name, 'symbol' => '£', 'exchange_rate' => 1,
        ]));

        $this->postJson(self::URI, $this->payload())
            ->assertStatus(409)
            ->assertExactJson(['message' => 'A currency with this name already exists.']);

        $this->assertDatabaseCount('currencies', 2);
    }

    public function test_concurrent_duplicate_on_update_returns_409(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create();

        Currency::updating(fn (Currency $updated) => DB::table('currencies')->insert([
            'name' => $updated->name, 'symbol' => '¤', 'exchange_rate' => 1,
        ]));

        $this->putJson(self::URI."/{$currency->id}", ['name' => 'XZZ'])->assertStatus(409);

        $this->assertDatabaseMissing('currencies', ['name' => 'XZZ']);
    }

    // --------------------------------------------------------------- update

    public function test_admin_updates_a_currency(): void
    {
        $this->actingAsAdmin();
        $eur = $this->eur();

        $this->putJson(self::URI."/{$eur->id}", ['exchange_rate' => 0.92])
            ->assertOk()
            ->assertJsonPath('data.exchange_rate', '0.920000')
            ->assertJsonPath('data.name', 'EUR');

        $this->assertSame('0.920000', $eur->fresh()->exchange_rate);
    }

    public function test_update_keeping_its_own_unique_values_is_valid(): void
    {
        $this->actingAsAdmin();
        $eur = $this->eur();

        $this->putJson(self::URI."/{$eur->id}", ['name' => 'EUR'])->assertOk();
    }

    public function test_update_cannot_take_another_currency_name(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI."/{$this->eur()->id}", ['name' => 'USD'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_seller_cannot_update_a_currency(): void
    {
        $this->actingAsSeller();
        $eur = $this->eur();

        $this->putJson(self::URI."/{$eur->id}", ['exchange_rate' => 5])->assertForbidden();
        $this->assertSame('0.890000', $eur->fresh()->exchange_rate);
    }

    public function test_update_unknown_currency_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI.'/999', ['exchange_rate' => 1.5])->assertNotFound();
    }

    public function test_guest_cannot_update_a_currency(): void
    {
        $this->putJson(self::URI."/{$this->eur()->id}", ['exchange_rate' => 5])->assertUnauthorized();
    }

    public function test_base_currency_rate_cannot_change(): void
    {
        $this->actingAsAdmin();
        $usd = $this->usd();

        $this->putJson(self::URI."/{$usd->id}", ['exchange_rate' => 1.5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exchange_rate' => 'The base currency exchange rate must be 1.']);

        $this->assertSame('1.000000', $usd->fresh()->exchange_rate);
    }

    public function test_base_currency_accepts_rate_one(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI."/{$this->usd()->id}", ['exchange_rate' => 1, 'symbol' => 'US$'])
            ->assertOk()
            ->assertJsonPath('data.symbol', 'US$');
    }

    public function test_base_currency_name_cannot_change(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI."/{$this->usd()->id}", ['name' => 'USN'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The base currency name cannot be changed.']);
    }

    // -------------------------------------------------------------- destroy

    public function test_admin_deletes_only_the_target_currency(): void
    {
        $this->actingAsAdmin();
        $gbp = Currency::factory()->create();

        $this->deleteJson(self::URI."/{$gbp->id}")->assertNoContent();

        $this->assertModelMissing($gbp);
        $this->assertDatabaseCount('currencies', 2);
    }

    public function test_seller_cannot_delete_a_currency(): void
    {
        $this->actingAsSeller();
        $currency = Currency::factory()->create();

        $this->deleteJson(self::URI."/{$currency->id}")->assertForbidden();
        $this->assertModelExists($currency);
    }

    public function test_delete_unknown_currency_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson(self::URI.'/999')->assertNotFound();
    }

    public function test_guest_cannot_delete_a_currency(): void
    {
        $this->deleteJson(self::URI."/{$this->eur()->id}")->assertUnauthorized();
    }

    public function test_base_currency_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $usd = $this->usd();

        $this->deleteJson(self::URI."/{$usd->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The base currency cannot be deleted.']);

        $this->assertModelExists($usd);
    }

    public function test_currency_used_as_product_base_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create();
        Product::factory()->for($currency)->create();

        $this->deleteJson(self::URI."/{$currency->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The currency is in use and cannot be deleted.']);

        $this->assertModelExists($currency);
    }

    public function test_currency_used_in_a_product_price_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $currency = Currency::factory()->create();
        ProductPrice::factory()->for($currency)->create();

        $this->deleteJson(self::URI."/{$currency->id}")->assertStatus(409);

        $this->assertModelExists($currency);
    }
}
