<?php

use App\Http\Controllers\Api\V1\Account\AddressController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Catalog\BookController;
use App\Http\Controllers\Api\V1\Location\LocationController;
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

// Public location proxy
Route::prefix('v1/locations')->middleware('throttle:120,1')->group(function (): void {
    // Get provinces
    Route::get('provinces', [LocationController::class, 'provinces']);

    // Get wards by province code
    Route::get('provinces/{provinceCode}/wards', [LocationController::class, 'wards']);
});

// Public catalog
Route::prefix('v1/books')->middleware('throttle:120,1')->group(function (): void {
    // Get filters
    Route::get('filters', [BookController::class, 'filters']);

    // List books
    Route::get('', [BookController::class, 'index']);

    // Get book
    Route::get('{slug}', [BookController::class, 'show']);
});

// Account routes
Route::prefix('v1/account')->middleware(['web', 'auth:sanctum'])->group(function (): void {
    // Get profile
    Route::get('profile', [ProfileController::class, 'show']);

    // Update profile
    Route::patch('profile', [ProfileController::class, 'update']);

    // Change password
    Route::patch('password', [PasswordController::class, 'update']);

    // List addresses
    Route::get('addresses', [AddressController::class, 'index']);

    // Create address
    Route::post('addresses', [AddressController::class, 'store']);

    // Update address
    Route::patch('addresses/{address}', [AddressController::class, 'update']);

    // Delete address
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
});
