<?php

use App\Http\Controllers\Api\V1\Account\AddressController;
use App\Http\Controllers\Api\V1\Account\OrderController;
use App\Http\Controllers\Api\V1\Account\ReviewController;
use App\Http\Controllers\Api\V1\Account\OrderRefundBankInfoController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Catalog\BookController;
use App\Http\Controllers\Api\V1\Catalog\BookReviewEligibilityController;
use App\Http\Controllers\Api\V1\Checkout\CheckoutController;
use App\Http\Controllers\Api\V1\Location\LocationController;
use App\Http\Controllers\Api\V1\Payment\VnPayReturnController;
use App\Http\Controllers\Api\V1\Shipping\ShippingQuoteController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::prefix('v1/auth')->group(function (): void {
    // Send OTP
    Route::post('register/send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:5,60');

    // Verify OTP for register
    Route::post('register/verify-otp', [AuthController::class, 'verifyOtp']);

    // Register
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('web');

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

// VNPay payment
Route::prefix('v1/payments/vnpay')->middleware('throttle:120,1')->group(function (): void {
    // VNPay return
    Route::get('return', VnPayReturnController::class);
});

// Public catalog
Route::prefix('v1/books')->middleware('throttle:120,1')->group(function (): void {
    // Get filters
    Route::get('filters', [BookController::class, 'filters']);

    // List books
    Route::get('', [BookController::class, 'index']);

    // Review eligibility for authenticated user on book detail page
    Route::get('{slug}/review-eligibility', [BookReviewEligibilityController::class, 'show'])
        ->middleware(['web', 'auth:sanctum', 'throttle:60,1']);

    // Get book
    Route::get('{slug}', [BookController::class, 'show']);
});

// Cart
Route::prefix('v1/cart')->middleware(['web', 'throttle:120,1'])->group(function (): void {
    // Get cart
    Route::get('', [CartController::class, 'show']);

    // Add item to cart
    Route::post('items', [CartController::class, 'addItem']);

    // Select or deselect all cart items
    Route::patch('items/selection', [CartController::class, 'updateItemsSelection']);

    // Update item in cart
    Route::patch('items/{cartItem}', [CartController::class, 'updateItem']);

    // Remove item from cart
    Route::delete('items/{cartItem}', [CartController::class, 'removeItem']);
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

    // List account orders
    Route::get('orders', [OrderController::class, 'index']);

    // Get order details
    Route::get('orders/{order}', [OrderController::class, 'show']);

    // Cancel order (customer)
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->middleware('throttle:10,1');

    // Get refund banks
    Route::get('refund-banks', [OrderRefundBankInfoController::class, 'banks']);

    // Submit refund bank info
    Route::post('orders/{order}/refund-bank-info', [OrderRefundBankInfoController::class, 'store'])
        ->middleware('throttle:10,1');

    // Submit review for a purchased order item
    Route::post('order-items/{orderItem}/review', [ReviewController::class, 'store'])
        ->middleware('throttle:10,1');
});

// Checkout
Route::post('v1/checkout', [CheckoutController::class, 'store'])
    ->middleware(['web', 'auth:sanctum', 'throttle:30,1']);

// Shipping quote
Route::post('v1/shipping/quote', [ShippingQuoteController::class, 'store'])
    ->middleware(['web', 'auth:sanctum', 'throttle:60,1']);
