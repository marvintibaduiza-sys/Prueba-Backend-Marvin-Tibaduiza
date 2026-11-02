<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class Controller
{
    public const SERVER_ERROR_MESSAGE = 'An unexpected error occurred.';

    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Resolve the page size from the "per_page" query parameter, clamped to a safe range.
     */
    protected function perPage(Request $request): int
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        if ($perPage === false) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Log an unexpected failure and return a generic 500 response without internal details.
     */
    protected function serverError(Throwable $e): JsonResponse
    {
        Log::error($e->getMessage(), ['exception' => $e]);

        return response()->json(['message' => self::SERVER_ERROR_MESSAGE], 500);
    }
}
