<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\FerryTripController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');

    // Same budget the API's register/login routes get.
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/login', [AuthenticatedSessionController::class, 'store']);
        Route::post('/register', [RegisteredUserController::class, 'store']);
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [FerryTripController::class, 'index'])->name('dashboard');
    Route::post('/ferry-trips/day', [FerryTripController::class, 'store'])->name('ferry-trips.day');
    Route::delete('/ferry-trips/{ferryTrip}', [FerryTripController::class, 'destroy'])->name('ferry-trips.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::get('/', fn () => redirect()->route('dashboard'));
