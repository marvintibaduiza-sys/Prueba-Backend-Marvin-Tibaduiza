<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccount(config('seeding.admin'), RoleName::Admin);
        $this->seedAccount(config('seeding.seller'), RoleName::Seller);
    }

    /**
     * @param  array{name: ?string, email: ?string, password: ?string}  $account
     */
    private function seedAccount(array $account, RoleName $role): void
    {
        if (blank($account['email']) || blank($account['password'])) {
            $this->command?->warn("Skipping {$role->value} account: email or password not configured.");

            return;
        }

        // Don't overwrite changes made through the API.
        $user = User::firstOrCreate(
            ['email' => $account['email']],
            ['name' => $account['name'], 'password' => $account['password']],
        );

        if ($user->wasRecentlyCreated) {
            $user->assignRole($role->value);
        }
    }
}
