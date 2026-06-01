<?php

use App\Http\Controllers\Api\V1\Ai\ChatController;
use App\Http\Controllers\Api\V1\Ai\ChatFeedbackController;
use App\Http\Controllers\Api\V1\Account\AddressController;
use App\Http\Controllers\Api\V1\Account\OrderController;
use App\Http\Controllers\Api\V1\Account\ReviewController;
use App\Http\Controllers\Api\V1\Account\WishlistController;
use App\Http\Controllers\Api\V1\Account\OrderRefundBankInfoController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Catalog\BookController;
use App\Http\Controllers\Api\V1\Promotion\FlashSaleController;
use App\Http\Controllers\Api\V1\Catalog\BookReviewController;
use App\Http\Controllers\Api\V1\Catalog\BookReviewEligibilityController;
use App\Http\Controllers\Api\V1\Recommendation\InteractionController;
use App\Http\Controllers\Api\V1\Recommendation\RecommendationController;
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

// Public Flash Sale
Route::prefix('v1/flash-sales')->middleware('throttle:120,1')->group(function (): void {
    Route::get('active', [FlashSaleController::class, 'active']);
});

// Public catalog
Route::prefix('v1/books')->middleware('throttle:120,1')->group(function (): void {
    // Get filters
    Route::get('filters', [BookController::class, 'filters']);

    // List books
    Route::get('', [BookController::class, 'index']);

    // Autocomplete suggestions (must be before {slug})
    Route::get('suggestions', [BookController::class, 'suggestions']);

    // Review eligibility for authenticated user on book detail page
    Route::get('{slug}/review-eligibility', [BookReviewEligibilityController::class, 'show'])
        ->middleware(['web', 'auth:sanctum', 'account.active', 'throttle:60,1']);

    // Approved reviews for book detail page (public)
    Route::get('{slug}/reviews', [BookReviewController::class, 'index']);

    // Get book
    Route::get('{slug}', [BookController::class, 'show']);
});

// Cart
Route::prefix('v1/cart')->middleware(['web', 'account.active', 'throttle:120,1'])->group(function (): void {
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
Route::prefix('v1/account')->middleware(['web', 'auth:sanctum', 'account.active'])->group(function (): void {
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

    // Regenerate VNPay payment URL for unpaid order
    Route::post('orders/{order}/vnpay/payment-url', [OrderController::class, 'vnpayPaymentUrl'])
        ->middleware('throttle:30,1');

    // Get refund banks
    Route::get('refund-banks', [OrderRefundBankInfoController::class, 'banks']);

    // Submit refund bank info
    Route::post('orders/{order}/refund-bank-info', [OrderRefundBankInfoController::class, 'store'])
        ->middleware('throttle:10,1');

    // Submit review for a purchased order item
    Route::post('order-items/{orderItem}/review', [ReviewController::class, 'store'])
        ->middleware('throttle:10,1');

    // Wishlist
    Route::get('wishlist', [WishlistController::class, 'index']);

    // Add item to wishlist
    Route::post('wishlist/items', [WishlistController::class, 'store']);

    // Remove item from wishlist
    Route::delete('wishlist/items/{book}', [WishlistController::class, 'destroy']);
});

// Checkout
Route::post('v1/checkout', [CheckoutController::class, 'store'])
    ->middleware(['web', 'auth:sanctum', 'account.active', 'throttle:30,1']);

// Shipping quote
Route::post('v1/shipping/quote', [ShippingQuoteController::class, 'store'])
    ->middleware(['web', 'auth:sanctum', 'account.active', 'throttle:60,1']);

// Recommendation interactions
Route::post('v1/recommendations/interactions/books/{book}/view', [InteractionController::class, 'trackBookView'])
    ->middleware(['web', 'auth:sanctum', 'account.active', 'throttle:60,1']);

// Recommendation feed
Route::get('v1/recommendations', [RecommendationController::class, 'index'])
    ->middleware(['web', 'throttle:120,1']);

// AI chatbot
Route::post('v1/ai/chat', [ChatController::class, 'store'])
    ->middleware(['web', 'throttle:ai-chat']);

Route::post('v1/ai/messages/{message}/feedback', [ChatFeedbackController::class, 'store'])
    ->middleware(['web', 'throttle:ai-chat-feedback']);
