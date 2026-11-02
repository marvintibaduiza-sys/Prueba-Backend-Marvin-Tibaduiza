<?php

namespace Tests\Feature\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_on_fresh_database_creates_roles_and_users(): void
    {
        config([
            'seeding.admin' => ['name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'admin-secret-1'],
            'seeding.seller' => ['name' => 'Seller', 'email' => 'seller@test.com', 'password' => 'seller-secret-1'],
        ]);

        $this->seed();

        $admin = User::where('email', 'admin@test.com')->firstOrFail();

        $this->assertTrue($admin->hasRole(RoleName::Admin->value));
        $this->assertTrue($admin->can('products.delete'));
    }
}
