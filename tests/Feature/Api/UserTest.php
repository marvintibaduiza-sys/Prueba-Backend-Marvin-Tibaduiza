<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/users';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(RoleName $role, array $attributes = []): User
    {
        return User::factory()->create($attributes)->assignRole($role->value);
    }

    /**
     * Valid data for the request. Pass null to drop a field.
     */
    private function payload(array $overrides = []): array
    {
        return array_filter(array_merge([
            'name' => 'New Seller',
            'email' => 'new.seller@test.com',
            'password' => 'strong-pass-1',
            'password_confirmation' => 'strong-pass-1',
            'role' => 'seller',
        ], $overrides), fn ($value) => $value !== null);
    }

    // ---------------------------------------------------------------- index

    public function test_admin_lists_users_paginated_with_roles(): void
    {
        $this->actingAsAdmin();
        $this->userWithRole(RoleName::Seller);

        $this->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'roles']], 'links', 'meta'])
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_seller_cannot_list_users(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI)->assertForbidden();
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    // ----------------------------------------------------------------- show

    public function test_admin_shows_a_user(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);

        $this->getJson(self::URI."/{$seller->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $seller->email)
            ->assertJsonPath('data.roles', ['seller'])
            ->assertJsonMissingPath('data.password');
    }

    public function test_seller_cannot_show_a_user(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI.'/'.User::factory()->create()->id)->assertForbidden();
    }

    public function test_show_unknown_user_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI.'/999')->assertNotFound();
    }

    public function test_guest_cannot_show_a_user(): void
    {
        $this->getJson(self::URI.'/'.User::factory()->create()->id)->assertUnauthorized();
    }

    // ---------------------------------------------------------------- store

    public function test_admin_creates_a_user_with_a_role_and_hashed_password(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::URI, $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'new.seller@test.com')
            ->assertJsonPath('data.roles', ['seller'])
            ->assertJsonMissingPath('data.password');
        $this->assertStringNotContainsString('strong-pass-1', $response->getContent());

        $user = User::where('email', 'new.seller@test.com')->firstOrFail();
        $this->assertNotSame('strong-pass-1', $user->password);
        $this->assertTrue(Hash::check('strong-pass-1', $user->password));
        $this->assertTrue($user->hasRole('seller'));
    }

    public function test_seller_cannot_create_a_user(): void
    {
        $this->actingAsSeller();

        $this->postJson(self::URI, $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new.seller@test.com']);
    }

    public function test_guest_cannot_create_a_user(): void
    {
        $this->postJson(self::URI, $this->payload())->assertUnauthorized();
    }

    public static function invalidStorePayloads(): array
    {
        return [
            'name missing' => [['name' => null], 'name'],
            'name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'email missing' => [['email' => null], 'email'],
            'email invalid' => [['email' => 'not-an-email'], 'email'],
            'email duplicated' => [['email' => 'taken@test.com'], 'email'],
            'password missing' => [['password' => null, 'password_confirmation' => null], 'password'],
            'password too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'password not confirmed' => [['password_confirmation' => 'something-else'], 'password'],
            'role missing' => [['role' => null], 'role'],
            'role unknown' => [['role' => 'superuser'], 'role'],
        ];
    }

    #[DataProvider('invalidStorePayloads')]
    public function test_store_validation(array $overrides, string $field): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['email' => 'taken@test.com']);

        $this->postJson(self::URI, $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseMissing('users', ['email' => 'new.seller@test.com']);
    }

    public function test_concurrent_duplicate_email_returns_409_and_rolls_back(): void
    {
        $this->actingAsAdmin();

        // Simulates another request registering the same email after validation passed.
        User::creating(fn (User $user) => DB::table('users')->insert([
            'name' => 'Concurrent', 'email' => $user->email, 'password' => 'x',
        ]));

        $this->postJson(self::URI, $this->payload())
            ->assertStatus(409)
            ->assertExactJson(['message' => 'A user with this email already exists.']);

        $this->assertDatabaseMissing('users', ['email' => 'new.seller@test.com']);
    }

    // --------------------------------------------------------------- update

    public function test_admin_updates_a_user_partially(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller, ['name' => 'Old Name']);

        $this->putJson(self::URI."/{$seller->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', $seller->email);
    }

    public function test_admin_changes_another_user_role(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);

        $this->putJson(self::URI."/{$seller->id}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.roles', ['admin']);

        $this->assertTrue($seller->fresh()->hasRole('admin'));
        $this->assertFalse($seller->fresh()->hasRole('seller'));
    }

    public function test_admin_changes_a_user_password(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);

        $this->putJson(self::URI."/{$seller->id}", [
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass-1', $seller->fresh()->password));
    }

    public function test_changing_a_user_password_revokes_its_tokens(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);
        $seller->createToken('stolen-session');

        $this->putJson(self::URI."/{$seller->id}", [
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $seller->id]);
    }

    public function test_changing_own_password_revokes_other_sessions_but_keeps_the_current_one(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $current = $admin->createToken('current');
        $other = $admin->createToken('other-device');

        $this->withToken($current->plainTextToken)->putJson(self::URI."/{$admin->id}", [
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertOk();

        $this->assertModelExists($current->accessToken);
        $this->assertModelMissing($other->accessToken);
    }

    public function test_updating_other_fields_keeps_tokens(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);
        $seller->createToken('session');

        $this->putJson(self::URI."/{$seller->id}", ['name' => 'Renamed'])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $seller->id]);
    }

    public function test_update_keeping_own_email_is_valid(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);

        $this->putJson(self::URI."/{$seller->id}", ['email' => $seller->email])->assertOk();
    }

    public function test_update_cannot_take_another_user_email(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);
        User::factory()->create(['email' => 'taken@test.com']);

        $this->putJson(self::URI."/{$seller->id}", ['email' => 'taken@test.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_seller_cannot_update_a_user(): void
    {
        $this->actingAsSeller();
        $other = User::factory()->create(['name' => 'Untouched']);

        $this->putJson(self::URI."/{$other->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->assertSame('Untouched', $other->fresh()->name);
    }

    public function test_update_unknown_user_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->putJson(self::URI.'/999', ['name' => 'Nobody'])->assertNotFound();
    }

    public function test_guest_cannot_update_a_user(): void
    {
        $this->putJson(self::URI.'/'.User::factory()->create()->id, ['name' => 'Hacked'])->assertUnauthorized();
    }

    // ------------------------------------------------------ lockout protection

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson(self::URI."/{$admin->id}", ['role' => 'seller'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'You cannot change your own role.']);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_admin_can_update_own_profile_keeping_the_same_role(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson(self::URI."/{$admin->id}", ['name' => 'Renamed Admin', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Admin');
    }

    public function test_admin_can_demote_another_admin_when_not_the_last_one(): void
    {
        $this->actingAsAdmin();
        $otherAdmin = $this->userWithRole(RoleName::Admin);

        $this->putJson(self::URI."/{$otherAdmin->id}", ['role' => 'seller'])->assertOk();

        $this->assertTrue($otherAdmin->fresh()->hasRole('seller'));
    }

    public function test_last_admin_cannot_be_demoted_or_deleted(): void
    {
        // An actor holding every permission passes delegation, so only the last-admin rule can stop it.
        $lastAdmin = $this->userWithRole(RoleName::Admin);
        $coAdmin = User::factory()->create();
        $coAdmin->assignRole(Role::create(['name' => 'co-admin', 'guard_name' => 'web'])->givePermissionTo(
            array_map(fn (PermissionName $permission) => $permission->value, PermissionName::cases())
        ));
        Sanctum::actingAs($coAdmin);

        $this->putJson(self::URI."/{$lastAdmin->id}", ['role' => 'seller'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The last administrator cannot be removed or demoted.']);

        $this->deleteJson(self::URI."/{$lastAdmin->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The last administrator cannot be removed or demoted.']);

        $this->assertTrue($lastAdmin->fresh()->hasRole('admin'));
    }

    // ------------------------------------------------- privilege escalation

    private function actingAsUserManager(array $extraPermissions = []): User
    {
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'manager', 'guard_name' => 'web'])->givePermissionTo([
            PermissionName::UsersView->value,
            PermissionName::UsersCreate->value,
            PermissionName::UsersUpdate->value,
            ...$extraPermissions,
        ]));
        Sanctum::actingAs($manager);

        return $manager;
    }

    public function test_actor_cannot_create_a_user_with_a_role_they_could_not_grant(): void
    {
        $this->actingAsUserManager();

        $this->postJson(self::URI, $this->payload(['role' => 'admin']))
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot assign a role with permissions you do not hold.']);

        $this->assertDatabaseMissing('users', ['email' => 'new.seller@test.com']);
    }

    public function test_actor_cannot_promote_a_user_to_a_role_they_could_not_grant(): void
    {
        // The manager holds the seller permissions, so it may manage the seller but not grant admin.
        $this->actingAsUserManager(array_map(
            fn (PermissionName $permission) => $permission->value,
            PermissionName::forRole(RoleName::Seller)
        ));
        $seller = $this->userWithRole(RoleName::Seller);

        $this->putJson(self::URI."/{$seller->id}", ['role' => 'admin'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot assign a role with permissions you do not hold.']);

        $this->assertFalse($seller->fresh()->hasRole('admin'));
    }

    public function test_actor_cannot_take_over_a_user_with_more_permissions(): void
    {
        $this->actingAsUserManager([PermissionName::UsersDelete->value]);
        $admin = $this->userWithRole(RoleName::Admin);
        $originalHash = $admin->password;

        $this->putJson(self::URI."/{$admin->id}", [
            'password' => 'taken-over-pass-1',
            'password_confirmation' => 'taken-over-pass-1',
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot manage a user with permissions you do not hold.']);

        $this->deleteJson(self::URI."/{$admin->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'You cannot manage a user with permissions you do not hold.']);

        $this->assertSame($originalHash, $admin->fresh()->password);
    }

    public function test_actor_can_assign_a_role_within_their_permissions(): void
    {
        $this->actingAsUserManager(array_map(
            fn (PermissionName $permission) => $permission->value,
            PermissionName::forRole(RoleName::Seller)
        ));

        $this->postJson(self::URI, $this->payload(['role' => 'seller']))->assertCreated();
    }

    public function test_user_with_several_roles_is_still_protected_as_last_admin(): void
    {
        // Only admin, but also holding the seller role: sending "seller" is still a demotion.
        $lastAdmin = $this->userWithRole(RoleName::Admin)->assignRole(RoleName::Seller->value);
        $coAdmin = User::factory()->create();
        $coAdmin->assignRole(Role::create(['name' => 'co-admin', 'guard_name' => 'web'])->givePermissionTo(
            array_map(fn (PermissionName $permission) => $permission->value, PermissionName::cases())
        ));
        Sanctum::actingAs($coAdmin);

        $this->putJson(self::URI."/{$lastAdmin->id}", ['role' => 'seller'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The last administrator cannot be removed or demoted.']);

        $this->assertTrue($lastAdmin->fresh()->hasRole('admin'));
    }

    public function test_user_with_several_roles_cannot_drop_own_admin_role(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->assignRole(RoleName::Seller->value);

        $this->putJson(self::URI."/{$admin->id}", ['role' => 'seller'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'You cannot change your own role.']);
    }

    public function test_users_without_user_permissions_cannot_probe_which_ids_exist(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI.'/999')->assertForbidden();
        $this->getJson(self::URI.'/'.User::factory()->create()->id)->assertForbidden();
    }

    // -------------------------------------------------------------- destroy

    public function test_admin_deletes_only_the_target_user_and_revokes_its_tokens(): void
    {
        $this->actingAsAdmin();
        $seller = $this->userWithRole(RoleName::Seller);
        $seller->createToken('api');
        $other = $this->userWithRole(RoleName::Seller);
        $other->createToken('api');

        $this->deleteJson(self::URI."/{$seller->id}")->assertNoContent();

        $this->assertModelMissing($seller);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $seller->id]);
        $this->assertModelExists($other);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $other->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->actingAsAdmin();
        $this->userWithRole(RoleName::Admin);

        $this->deleteJson(self::URI."/{$admin->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'You cannot delete your own account.']);

        $this->assertModelExists($admin);
    }

    public function test_seller_cannot_delete_a_user(): void
    {
        $this->actingAsSeller();
        $other = User::factory()->create();

        $this->deleteJson(self::URI."/{$other->id}")->assertForbidden();
        $this->assertModelExists($other);
    }

    public function test_delete_unknown_user_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson(self::URI.'/999')->assertNotFound();
    }

    public function test_guest_cannot_delete_a_user(): void
    {
        $user = User::factory()->create();

        $this->deleteJson(self::URI."/{$user->id}")->assertUnauthorized();
        $this->assertModelExists($user);
    }
}
