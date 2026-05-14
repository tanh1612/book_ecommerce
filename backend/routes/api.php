<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::prefix('v1/auth')->group(function (): void {
    // Send OTP
    Route::post('register/send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:5,60');

    // Verify OTP
    Route::post('register/verify-otp', [AuthController::class, 'verifyOtp']);

    // Register
    Route::post('register', [AuthController::class, 'register']);

    // Login
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('web');

    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware(['web', 'auth:sanctum']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
