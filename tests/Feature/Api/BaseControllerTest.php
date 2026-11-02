<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class BaseControllerTest extends TestCase
{
    private function probeController(): Controller
    {
        return new class extends Controller
        {
            public function exposePerPage(Request $request): int
            {
                return $this->perPage($request);
            }

            public function exposeServerError(Throwable $e): JsonResponse
            {
                return $this->serverError($e);
            }
        };
    }

    public static function perPageCases(): array
    {
        return [
            'absent' => ['', 15],
            'valid' => ['?per_page=30', 30],
            'zero' => ['?per_page=0', 1],
            'negative' => ['?per_page=-1', 1],
            'not numeric' => ['?per_page=abc', 15],
            'too large' => ['?per_page=100000', 100],
        ];
    }

    #[DataProvider('perPageCases')]
    public function test_per_page_is_clamped(string $query, int $expected): void
    {
        $controller = $this->probeController();
        Route::get('/api/_per_page', fn (Request $request) => ['per_page' => $controller->exposePerPage($request)]);

        $this->getJson('/api/_per_page'.$query)
            ->assertOk()
            ->assertExactJson(['per_page' => $expected]);
    }

    public function test_server_error_logs_and_returns_generic_500(): void
    {
        Log::shouldReceive('error')->once();
        $controller = $this->probeController();
        Route::get('/api/_server_error', fn () => $controller->exposeServerError(new RuntimeException('secret')));

        $response = $this->getJson('/api/_server_error');

        $response->assertStatus(500)
            ->assertExactJson(['message' => 'An unexpected error occurred.']);
        $this->assertStringNotContainsString('secret', $response->getContent());
    }
}
