<?php

namespace Tests\Feature\Docs;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    private static ?array $document = null;

    /**
     * Generate the docs once for all the tests.
     */
    private function document(): array
    {
        if (self::$document === null) {
            $this->assertSame(0, Artisan::call('l5-swagger:generate'), Artisan::output());
            self::$document = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true, flags: JSON_THROW_ON_ERROR);
        }

        return self::$document;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function apiOperations(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            // Only our API controllers (the Swagger UI routes also live under api/).
            ->filter(fn (RouteDefinition $route) => str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\Api\\'))
            ->flatMap(fn (RouteDefinition $route) => collect($route->methods())
                ->reject(fn (string $method) => in_array($method, ['HEAD', 'PATCH'], true))
                ->map(fn (string $method) => [strtolower($method), '/'.substr($route->uri(), strlen('api/'))]))
            ->values()
            ->all();
    }

    public function test_every_api_route_is_documented_with_an_operation_id(): void
    {
        $paths = $this->document()['paths'];

        foreach ($this->apiOperations() as [$method, $path]) {
            $this->assertArrayHasKey($path, $paths, "Path {$path} is not documented.");
            $this->assertArrayHasKey($method, $paths[$path], strtoupper($method)." {$path} is not documented.");
            $this->assertNotEmpty($paths[$path][$method]['operationId'] ?? null, strtoupper($method)." {$path} has no operationId.");
        }
    }

    public function test_every_operation_except_login_requires_the_bearer_token(): void
    {
        foreach ($this->document()['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if ($path === '/auth/login') {
                    $this->assertEmpty($operation['security'] ?? [], 'Login must be public.');

                    continue;
                }

                $this->assertSame([['sanctum' => []]], $operation['security'] ?? null, strtoupper($method)." {$path} is not secured.");
            }
        }
    }

    public function test_operation_ids_are_unique(): void
    {
        $ids = collect($this->document()['paths'])->flatMap(fn (array $operations) => collect($operations)->pluck('operationId'));

        $this->assertSame($ids->count(), $ids->unique()->count());
    }

    public function test_bearer_security_scheme_is_declared(): void
    {
        $scheme = $this->document()['components']['securitySchemes']['sanctum'] ?? null;

        $this->assertSame('http', $scheme['type'] ?? null);
        $this->assertSame('bearer', $scheme['scheme'] ?? null);
    }

    public function test_exchange_rate_meaning_is_documented(): void
    {
        $description = $this->document()['components']['schemas']['Currency']['properties']['exchange_rate']['description'] ?? '';

        $this->assertStringContainsString('per 1 USD', $description);
    }

    public function test_documentation_ui_is_served(): void
    {
        $this->get('/api/documentation')->assertOk();
    }
}
