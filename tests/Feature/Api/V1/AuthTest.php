<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-pass-1';

    private function createUser(RoleName $role = RoleName::Seller): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['email' => 'user@test.com', 'password' => self::PASSWORD]);
        $user->assignRole($role->value);

        return $user;
    }

    private function login(string $email = 'user@test.com', string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_login_returns_bearer_token_with_expiration(): void
    {
        $this->freezeTime();
        $user = $this->createUser();

        $response = $this->login();

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', now()->addMinutes(60)->toIso8601String())
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.roles', ['seller'])
            ->assertJsonMissingPath('data.user.password');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_with_wrong_password_returns_422_generic_message(): void
    {
        $this->createUser();

        $this->login(password: 'wrong-password')
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    public function test_login_with_unknown_email_returns_the_same_generic_message(): void
    {
        $this->createUser();

        $this->login(email: 'nobody@test.com')
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    public function test_unknown_email_still_runs_a_password_hash_check(): void
    {
        $this->createUser();
        Hash::spy();

        $this->login(email: 'nobody@test.com')->assertUnprocessable();

        // Equal work on both paths: response time must not reveal whether the email exists.
        Hash::shouldHaveReceived('check')->once();
    }

    public static function invalidLoginPayloads(): array
    {
        return [
            'missing email' => [['password' => 'secret'], 'email'],
            'invalid email' => [['email' => 'not-an-email', 'password' => 'secret'], 'email'],
            'missing password' => [['email' => 'user@test.com'], 'password'],
        ];
    }

    #[DataProvider('invalidLoginPayloads')]
    public function test_login_validation(array $payload, string $field): void
    {
        $this->postJson('/api/v1/auth/login', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_malformed_email_returns_422_and_is_still_throttled(): void
    {
        $payload = ['email' => ['user@test.com'], 'password' => 'secret'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertTooManyRequests();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $this->createUser();

        for ($i = 0; $i < 5; $i++) {
            $this->login(password: 'wrong-password')->assertUnprocessable();
        }

        $this->login()->assertTooManyRequests();
    }

    public function test_me_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_me_returns_current_user_with_roles_and_permissions(): void
    {
        $user = $this->createUser();
        $token = $this->login()->json('data.token');

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'user@test.com')
            ->assertJsonPath('data.roles', ['seller'])
            ->assertJsonMissingPath('data.password');
        $this->assertContains('products.view', $response->json('data.permissions'));
        $this->assertNotContains('products.delete', $response->json('data.permissions'));
    }

    public function test_logout_revokes_current_token(): void
    {
        $this->createUser();
        $token = $this->login()->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Logged out.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->createUser();
        $token = $this->login()->json('data.token');

        $this->travel(61)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_token_is_still_valid_before_expiration(): void
    {
        $this->createUser();
        $token = $this->login()->json('data.token');

        $this->travel(59)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }
}
