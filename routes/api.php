<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public: the only endpoint reachable without a token.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', 'logout')->name('logout');
            Route::get('me', 'me')->name('me');
        });

        Route::apiResource('currencies', CurrencyController::class);
        Route::apiResource('products', ProductController::class);
    });
});
