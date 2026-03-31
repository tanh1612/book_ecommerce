<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Books - filter/sort trên listing page
        Schema::table('books', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('selling_price');
            $table->index('average_rating');
            $table->index('created_at');
        });

        // Categories - filter active categories
        Schema::table('categories', function (Blueprint $table) {
            $table->index('is_active');
        });

        // Warehouses - filter active warehouses
        Schema::table('warehouses', function (Blueprint $table) {
            $table->index('is_active');
        });

        // Orders - filter/search theo status, thời gian
        Schema::table('orders', function (Blueprint $table) {
            $table->index('current_status');
            $table->index('payment_status');
            $table->index('created_at');
            $table->index(['account_id', 'current_status']);
        });

        // Order items - check reviewed status
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('is_reviewed');
        });

        // Promotions - tìm promotions đang active
        Schema::table('promotions', function (Blueprint $table) {
            $table->index('status');
            $table->index(['start_at', 'end_at']);
        });

        // Reviews - filter theo status, aggregate rating
        Schema::table('reviews', function (Blueprint $table) {
            $table->index('status');
            $table->index(['book_id', 'rating']);
        });

        // Shipping rates - lookup theo tỉnh
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->index('province_code');
        });

        // Addresses - tìm địa chỉ mặc định
        Schema::table('addresses', function (Blueprint $table) {
            $table->index(['account_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['selling_price']);
            $table->dropIndex(['average_rating']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['current_status']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['account_id', 'current_status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['is_reviewed']);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['start_at', 'end_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['book_id', 'rating']);
        });

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->dropIndex(['province_code']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'is_default']);
        });
    }
};
