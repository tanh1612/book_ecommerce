<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_item_id')->constrained('promotion_items')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 50);
            $table->timestamps();

            $table->index(['promotion_item_id', 'account_id', 'status']);
            $table->index('order_id');
            $table->index('order_item_id');
        });

        DB::statement('ALTER TABLE promotion_allocations ADD CONSTRAINT promotion_allocations_quantity_positive_check CHECK (quantity > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE promotion_allocations DROP CONSTRAINT promotion_allocations_quantity_positive_check');

        Schema::dropIfExists('promotion_allocations');
    }
};
