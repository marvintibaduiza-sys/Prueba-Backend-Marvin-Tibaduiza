<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        // Protected resources are registered here in the following steps.
    });
});
