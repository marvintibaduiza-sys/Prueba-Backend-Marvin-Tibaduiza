<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/roles';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function role(string $name): Role
    {
        return Role::findByName($name, 'web');
    }

    private function customRole(string $name = 'auditor', array $permissions = ['products.view']): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web'])->givePermissionTo($permissions);
    }

    /**
     * A valid store payload; a null override removes that field.
     */
    private function payload(array $overrides = []): array
    {
        return array_filter(
            array_merge(['name' => 'auditor', 'permissions' => ['products.view', 'prices.view']], $overrides),
            fn ($value) => $value !== null
        );
    }

    // ---------------------------------------------------------------- index

    public function test_admin_lists_roles_with_permissions(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'permissions', 'users_count']], 'links', 'meta']);
    }

    public function test_seller_cannot_list_roles(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI)->assertForbidden();
    }

    public function test_guest_cannot_list_roles(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    // ----------------------------------------------------------------- show

    public function test_admin_shows_a_role_with_its_permissions_and_users_count(): void
    {
        $this->actingAsAdmin();
        $seller = $this->role('seller');
        User::factory()->create()->assignRole($seller);

        $this->getJson(self::URI."/{$seller->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'seller')
            ->assertJsonPath('data.users_count', 1)
            ->assertJsonPath('data.permissions', [
                'currencies.view', 'prices.create', 'prices.view', 'products.create', 'products.update', 'products.view',
            ]);
    }

    public function test_seller_cannot_show_a_role(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI."/{$this->role('seller')->id}")->assertForbidden();
    }

    public function test_show_unknown_role_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI.'/999')->assertNotFound();
    }

    public function test_guest_cannot_show_a_role(): void
    {
        $this->getJson(self::URI."/{$this->role('seller')->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------- store

    public function test_admin_creates_a_role_with_permissions(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'auditor')
            ->assertJsonPath('data.permissions', ['prices.view', 'products.view']);

        $role = $this->role('auditor');
        $this->assertTrue($role->hasPermissionTo('products.view'));
        $this->assertFalse($role->hasPermissionTo('products.delete'));
    }

    public function test_seller_cannot_create_a_role(): void
    {
        $this->actingAsSeller();

        $this->postJson(self::URI, $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'auditor']);
    }

    public function test_guest_cannot_create_a_role(): void
    {
        $this->postJson(self::URI, $this->payload())->assertUnauthorized();
    }

    public static function invalidStorePayloads(): array
    {
        return [
            'name missing' => [['name' => null], 'name'],
            'name uppercase' => [['name' => 'Auditor'], 'name'],
            'name with spaces' => [['name' => 'sales team'], 'name'],
            'name too long' => [['name' => str_repeat('a', 51)], 'name'],
            'name duplicated' => [['name' => 'seller'], 'name'],
            'permissions missing' => [['permissions' => null], 'permissions'],
            'permissions empty' => [['permissions' => []], 'permissions'],
            'permissions not a list' => [['permissions' => 'products.view'], 'permissions'],
            'permission unknown' => [['permissions' => ['products.fly']], 'permissions.0'],
            'permission duplicated' => [['permissions' => ['products.view', 'products.view']], 'permissions.0'],
        ];
    }

    #[DataProvider('invalidStorePayloads')]
    public function test_store_validation(array $overrides, string $field): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URI, $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('roles', 2);
    }

    public function test_concurrent_duplicate_role_returns_409_and_rolls_back(): void
    {
        $this->actingAsAdmin();

        // Simulates another request creating the same role after validation passed.
        Role::creating(fn (Role $role) => DB::table('roles')->insert([
            'name' => $role->name, 'guard_name' => $role->guard_name,
        ]));

        $this->postJson(self::URI, $this->payload())
            ->assertStatus(409)
            ->assertExactJson(['message' => 'A role with this name already exists.']);

        $this->assertDatabaseMissing('roles', ['name' => 'auditor']);
    }

    // --------------------------------------------------------------- update

    public function test_admin_updates_a_role_name_and_permissions(): void
    {
        $this->actingAsAdmin();
        $role = $this->customRole();

        $this->putJson(self::URI."/{$role->id}", ['name' => 'reviewer', 'permissions' => ['currencies.view']])
            ->assertOk()
            ->assertJsonPath('data.name', 'reviewer')
            ->assertJsonPath('data.permissions', ['currencies.view']);
    }

    public function test_update_accepts_partial_payload(): void
    {
        $this->actingAsAdmin();
        $role = $this->customRole();

        $this->putJson(self::URI."/{$role->id}", ['permissions' => ['products.view', 'products.delete']])
            ->assertOk()
            ->assertJsonPath('data.name', 'auditor')
            ->assertJsonPath('data.permissions', ['products.delete', 'products.view']);
    }

    public function test_update_keeping_own_name_is_valid(): void
    {
        $this->actingAsAdmin();
        $role = $this->customRole();

        $this->putJson(self::URI."/{$role->id}", ['name' => 'auditor'])->assertOk();
    }

    public function test_update_cannot_take_another_role_name(): void
    {
        $this->actingAsAdmin();
        $role = $this->customRole();

        $this->putJson(self::URI."/{$role->id}", ['name' => 'seller'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_granting_a_permission_to_seller_takes_effect_immediately(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI."/{$this->role('seller')->id}", [
            'permissions' => ['products.view', 'products.delete'],
        ])->assertOk();

        $seller = User::factory()->create()->assignRole('seller');
        Sanctum::actingAs($seller);
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/products/{$product->id}")->assertNoContent();
    }

    public function test_admin_role_cannot_be_modified(): void
    {
        $this->actingAsAdmin();
        $admin = $this->role('admin');

        $this->putJson(self::URI."/{$admin->id}", ['permissions' => ['products.view']])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The admin role cannot be modified or deleted.']);

        $this->assertTrue($admin->fresh()->hasPermissionTo('users.delete'));
    }

    public function test_seller_cannot_update_a_role(): void
    {
        $this->actingAsSeller();
        $seller = $this->role('seller');

        $this->putJson(self::URI."/{$seller->id}", ['permissions' => ['users.delete']])->assertForbidden();
        $this->assertFalse($seller->fresh()->hasPermissionTo('users.delete'));
    }

    public function test_update_unknown_role_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI.'/999', ['name' => 'ghost'])->assertNotFound();
    }

    public function test_guest_cannot_update_a_role(): void
    {
        $this->putJson(self::URI."/{$this->role('seller')->id}", ['name' => 'hacked'])->assertUnauthorized();
    }

    // -------------------------------------------------------------- destroy

    public function test_admin_deletes_only_the_target_unassigned_role(): void
    {
        $this->actingAsAdmin();
        $role = $this->customRole();
        $other = $this->customRole('reviewer');

        $this->deleteJson(self::URI."/{$role->id}")->assertNoContent();

        $this->assertModelMissing($role);
        $this->assertModelExists($other);
    }

    public function test_role_assigned_to_users_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $role = $this->customRole();
        User::factory()->create()->assignRole($role);

        $this->deleteJson(self::URI."/{$role->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The role is assigned to users and cannot be deleted.']);

        $this->assertModelExists($role);
    }

    public function test_admin_role_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $admin = $this->role('admin');

        $this->deleteJson(self::URI."/{$admin->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The admin role cannot be modified or deleted.']);

        $this->assertModelExists($admin);
    }

    public function test_seller_cannot_delete_a_role(): void
    {
        $this->actingAsSeller();
        $role = $this->customRole();

        $this->deleteJson(self::URI."/{$role->id}")->assertForbidden();
        $this->assertModelExists($role);
    }

    public function test_delete_unknown_role_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson(self::URI.'/999')->assertNotFound();
    }

    public function test_guest_cannot_delete_a_role(): void
    {
        $this->deleteJson(self::URI."/{$this->customRole()->id}")->assertUnauthorized();
    }

    // ------------------------------------------------- privilege escalation

    private function actingAsRoleManager(): User
    {
        $manager = User::factory()->create()->assignRole(
            $this->customRole('manager', ['roles.view', 'roles.create', 'roles.update', 'products.view'])
        );
        Sanctum::actingAs($manager);

        return $manager;
    }

    public function test_actor_cannot_create_a_role_with_permissions_they_do_not_hold(): void
    {
        $this->actingAsRoleManager();

        $this->postJson(self::URI, ['name' => 'superuser', 'permissions' => ['products.view', 'users.delete']])
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot grant permissions you do not hold.']);

        $this->assertDatabaseMissing('roles', ['name' => 'superuser']);
    }

    public function test_actor_cannot_escalate_their_own_role(): void
    {
        $this->actingAsRoleManager();
        $managerRole = $this->role('manager');

        $this->putJson(self::URI."/{$managerRole->id}", ['permissions' => ['roles.update', 'users.delete']])
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot grant permissions you do not hold.']);

        $this->assertFalse($managerRole->fresh()->hasPermissionTo('users.delete'));
    }

    public function test_actor_cannot_strip_permissions_from_a_more_powerful_role(): void
    {
        $this->actingAsRoleManager();
        $coAdmin = $this->customRole('co-admin', Permission::pluck('name')->all());

        $this->putJson(self::URI."/{$coAdmin->id}", ['permissions' => ['products.view']])
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot manage a role with permissions you do not hold.']);

        $this->assertCount(18, $coAdmin->fresh()->permissions);
    }

    public function test_actor_can_grant_permissions_they_hold(): void
    {
        $this->actingAsRoleManager();

        $this->postJson(self::URI, ['name' => 'viewer', 'permissions' => ['products.view']])->assertCreated();
    }

    // ------------------------------------------------------------ end to end

    public function test_new_role_grants_exactly_its_permissions(): void
    {
        $this->actingAsAdmin();
        $this->postJson(self::URI, ['name' => 'auditor', 'permissions' => ['products.view']])->assertCreated();

        $auditor = User::factory()->create()->assignRole('auditor');
        Sanctum::actingAs($auditor);
        $product = Product::factory()->create();

        $this->getJson('/api/v1/products')->assertOk();
        $this->deleteJson("/api/v1/products/{$product->id}")->assertForbidden();
        $this->getJson('/api/v1/currencies')->assertForbidden();
    }
}
