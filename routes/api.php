<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FerryTripController;
use Illuminate\Support\Facades\Route;

// Public — max 5 login/register attempts per minute per IP
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Everything below needs "Authorization: Bearer <token>"
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('ferry-trips')->controller(FerryTripController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/summary', 'summary');
        Route::post('/day', 'storeDay');
        Route::delete('/{ferryTrip}', 'destroy');
    });
});
