<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/users';

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
     * A valid store payload; a null override removes that field.
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
        $lastAdmin = $this->userWithRole(RoleName::Admin);
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'manager'])->givePermissionTo([
            PermissionName::UsersView->value,
            PermissionName::UsersUpdate->value,
            PermissionName::UsersDelete->value,
        ]));
        Sanctum::actingAs($manager);

        $this->putJson(self::URI."/{$lastAdmin->id}", ['role' => 'seller'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The last administrator cannot be removed or demoted.']);

        $this->deleteJson(self::URI."/{$lastAdmin->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'The last administrator cannot be removed or demoted.']);

        $this->assertTrue($lastAdmin->fresh()->hasRole('admin'));
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
