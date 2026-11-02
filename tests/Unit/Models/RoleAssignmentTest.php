<?php

namespace Tests\Unit\Models;

use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_spatie_role_assignment_table(): void
    {
        $this->assertSame(config('permission.table_names.model_has_roles'), (new RoleAssignment)->getTable());
    }

    public function test_it_reads_the_users_holding_a_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $seller = User::factory()->create()->assignRole('seller');
        User::factory()->create()->assignRole('admin');

        $sellerRole = Role::findByName('seller', 'web');

        $this->assertSame([$seller->id], RoleAssignment::ofRole($sellerRole)->forUsers()->pluck('model_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_it_belongs_to_its_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->create()->assignRole('seller');

        $this->assertSame('seller', RoleAssignment::first()->role->name);
    }
}
