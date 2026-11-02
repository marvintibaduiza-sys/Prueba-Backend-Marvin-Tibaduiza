<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Rates are "units of the currency per 1 USD" (USD is the base: 1 USD = 0.89 EUR).
     */
    private const CURRENCIES = [
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 0.89],
    ];

    public function run(): void
    {
        foreach (self::CURRENCIES as $currency) {
            // firstOrCreate: re-seeding never overwrites a rate updated later through the API.
            Currency::firstOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
