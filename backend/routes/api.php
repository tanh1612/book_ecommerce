<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('register/send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:5,60');

    Route::post('register/verify-otp', [AuthController::class, 'verifyOtp']);

    Route::post('register', [AuthController::class, 'register'])
        ->middleware('web');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
