<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\ConflictException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_route_returns_json_404_without_accept_header(): void
    {
        $this->get('/api/v1/nope')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_unknown_model_id_returns_json_404_without_leaking_model_name(): void
    {
        Route::middleware(SubstituteBindings::class)
            ->get('/api/v1/_users/{user}', fn (User $user) => $user);

        $this->get('/api/v1/_users/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);

        $this->get('/api/v1/_users/abc')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_protected_route_without_token_returns_json_401_without_accept_header(): void
    {
        Route::middleware('auth:sanctum')->get('/api/v1/_probe', fn () => 'ok');

        $this->get('/api/v1/_probe')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_authorization_exception_renders_403(): void
    {
        Route::get('/api/v1/_forbidden', fn () => throw new AuthorizationException);

        $this->get('/api/v1/_forbidden')
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);
    }

    public function test_conflict_exception_renders_409(): void
    {
        Route::get('/api/v1/_conflict', fn () => throw new ConflictException('Busy.'));

        $this->get('/api/v1/_conflict')
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Busy.']);
    }

    public function test_unexpected_exception_renders_generic_500_without_details(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/_boom', fn () => throw new RuntimeException('secret'));

        $response = $this->get('/api/v1/_boom');

        $response->assertStatus(500)
            ->assertExactJson(['message' => 'An unexpected error occurred.']);
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_api_throttle_returns_429_after_60_requests(): void
    {
        Route::middleware(['auth:sanctum', 'throttle:api'])->get('/api/v1/_throttled', fn () => 'ok');
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->get('/api/v1/_throttled')->assertOk();
        }

        $this->get('/api/v1/_throttled')
            ->assertTooManyRequests()
            ->assertJsonStructure(['message']);
    }
}
