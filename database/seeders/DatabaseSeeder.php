<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Keep model events on: spatie needs them to refresh its permission cache.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CurrencySeeder::class,
            UserSeeder::class,
        ]);
    }
}
