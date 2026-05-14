<?php

use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::prefix('v1/auth')->group(function (): void {
    // Send OTP
    Route::post('register/send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:5,60');

    // Verify OTP for register
    Route::post('register/verify-otp', [AuthController::class, 'verifyOtp']);

    // Register
    Route::post('register', [AuthController::class, 'register']);

    // Forgot password
    Route::post('password/forgot/send-otp', [ForgotPasswordController::class, 'sendOtp'])
        ->middleware('throttle:5,60');

    // Verify OTP for forgot password
    Route::post('password/forgot/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);

    // Reset password
    Route::post('password/reset', [ForgotPasswordController::class, 'reset']);

    // Login
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('web');

    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware(['web', 'auth:sanctum']);
});

// Account routes
Route::prefix('v1/account')->middleware(['web', 'auth:sanctum'])->group(function (): void {
    // Get profile
    Route::get('profile', [ProfileController::class, 'show']);

    // Update profile
    Route::patch('profile', [ProfileController::class, 'update']);

    // Change password
    Route::patch('password', [PasswordController::class, 'update']);
});
