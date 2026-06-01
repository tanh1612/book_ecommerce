<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('checkout_idempotency_key', 36)->nullable();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->restrictOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('shipping_fee', 15, 2);
            $table->decimal('final_amount', 15, 2);
            $table->string('shipping_name', 100);
            $table->string('shipping_phone', 20);
            $table->text('shipping_address');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_status', 50)->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('refund_deadline_at')->nullable();
            $table->text('note')->nullable();
            $table->string('current_status', 50);
            $table->timestamps();

            $table->index('current_status');
            $table->index('payment_status');
            $table->index('created_at');
            $table->index(['account_id', 'current_status']);
            $table->unique(['account_id', 'checkout_idempotency_key'], 'orders_account_checkout_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
