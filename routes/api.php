<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductPriceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Laravel serves this file under the /api prefix (e.g. /api/products).

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
    Route::apiResource('products.prices', ProductPriceController::class)->only(['index', 'store']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
});
