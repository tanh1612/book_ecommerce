<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->decimal('discount_value', 5, 2);
            $table->unsignedInteger('stock_limit')->nullable();
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->unsignedSmallInteger('max_quantity_per_user')->nullable();
            $table->timestamps();

            $table->unique(['promotion_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_items');
    }
};
