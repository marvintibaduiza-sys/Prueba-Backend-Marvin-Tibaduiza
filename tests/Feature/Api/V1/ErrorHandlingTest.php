<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\ConflictException;
use App\Models\Currency;
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
            ->get('/api/v1/_currencies/{currency}', fn (Currency $currency) => $currency);

        $this->get('/api/v1/_currencies/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);

        $this->get('/api/v1/_currencies/abc')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    /**
     * MySQL casts '1abc' to 1 when comparing with an integer key, so a route like
     * /products/1abc would load product 1. Ids must be rejected at the routing level.
     */
    public function test_resource_ids_only_match_digits_in_every_route(): void
    {
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_intersect($route->parameterNames(), ['product', 'currency', 'user', 'role']) as $parameter) {
                $this->assertSame('[0-9]+', $route->wheres[$parameter] ?? null, "{$route->uri()} accepts non-numeric {$parameter}.");
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked);
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
