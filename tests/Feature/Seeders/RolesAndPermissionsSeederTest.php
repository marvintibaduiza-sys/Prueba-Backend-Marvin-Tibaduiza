<?php

namespace Tests\Feature\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function permissionNamesOf(RoleName $role): array
    {
        return Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();
    }

    private function allPermissionNames(): array
    {
        return collect(PermissionName::cases())->map->value->sort()->values()->all();
    }

    public function test_seeder_creates_all_18_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(18, Permission::count());
        $this->assertSame($this->allPermissionNames(), Permission::pluck('name')->sort()->values()->all());
    }

    public function test_admin_role_has_every_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame($this->allPermissionNames(), $this->permissionNamesOf(RoleName::Admin));
    }

    public function test_seller_can_view_create_update_products_view_create_prices_and_view_currencies(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame([
            'currencies.view',
            'prices.create',
            'prices.view',
            'products.create',
            'products.update',
            'products.view',
        ], $this->permissionNamesOf(RoleName::Seller));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(18, Permission::count());
        $this->assertSame(2, Role::count());
    }
}
