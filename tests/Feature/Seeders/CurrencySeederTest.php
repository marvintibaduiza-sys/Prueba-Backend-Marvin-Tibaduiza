<?php

namespace Tests\Feature\Seeders;

use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_usd_with_rate_one_and_eur_with_rate_0_89(): void
    {
        $this->seed(CurrencySeeder::class);

        $this->assertDatabaseCount('currencies', 2);
        $this->assertDatabaseHas('currencies', ['name' => 'USD', 'symbol' => '$']);
        $this->assertDatabaseHas('currencies', ['name' => 'EUR', 'symbol' => '€']);
        $this->assertSame('1.000000', Currency::where('name', 'USD')->first()->exchange_rate);
        $this->assertSame('0.890000', Currency::where('name', 'EUR')->first()->exchange_rate);
    }

    public function test_seeder_is_idempotent_and_keeps_rates_changed_later(): void
    {
        $this->seed(CurrencySeeder::class);
        Currency::where('name', 'EUR')->update(['exchange_rate' => 0.95]);

        $this->seed(CurrencySeeder::class);

        $this->assertDatabaseCount('currencies', 2);
        $this->assertSame('0.950000', Currency::where('name', 'EUR')->first()->exchange_rate);
    }
}
