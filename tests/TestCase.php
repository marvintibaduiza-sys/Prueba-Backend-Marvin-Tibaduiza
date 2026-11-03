<?php

namespace Tests;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function actingAsRole(RoleName $role): User
    {
        if (Role::query()->doesntExist()) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }

        $user = User::factory()->create();
        $user->assignRole($role->value);

        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsAdmin(): User
    {
        return $this->actingAsRole(RoleName::Admin);
    }

    protected function actingAsSeller(): User
    {
        return $this->actingAsRole(RoleName::Seller);
    }
}
