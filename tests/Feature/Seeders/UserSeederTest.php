<?php

namespace Tests\Feature\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seeding.admin' => ['name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'admin-secret-1'],
            'seeding.seller' => ['name' => 'Seller', 'email' => 'seller@test.com', 'password' => 'seller-secret-1'],
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creates_admin_and_seller_from_config(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@test.com')->firstOrFail();
        $seller = User::where('email', 'seller@test.com')->firstOrFail();

        $this->assertTrue($admin->hasRole(RoleName::Admin->value));
        $this->assertTrue($seller->hasRole(RoleName::Seller->value));
        $this->assertTrue(Hash::check('admin-secret-1', $admin->password));
        $this->assertTrue(Hash::check('seller-secret-1', $seller->password));
    }

    public function test_skips_account_without_credentials(): void
    {
        config(['seeding.seller.password' => null]);

        $this->seed(UserSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@test.com']);
        $this->assertDatabaseMissing('users', ['email' => 'seller@test.com']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertCount(1, User::where('email', 'admin@test.com')->firstOrFail()->roles);
    }
}
