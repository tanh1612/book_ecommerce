<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT promotions_end_after_start_check CHECK (end_at > start_at)');
        DB::statement('ALTER TABLE promotion_items ADD CONSTRAINT promotion_items_discount_percent_check CHECK (discount_value > 0 AND discount_value <= 100 AND discount_value = FLOOR(discount_value))');
        DB::statement('ALTER TABLE promotion_items ADD CONSTRAINT promotion_items_sold_within_stock_check CHECK (stock_limit IS NULL OR sold_quantity <= stock_limit)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range_check CHECK (rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_reserved_within_quantity_check CHECK (reserved_quantity <= quantity)');
        DB::statement('ALTER TABLE books ADD CONSTRAINT books_prices CHECK (original_price > 0 AND selling_price > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE promotions DROP CONSTRAINT promotions_end_after_start_check');
        DB::statement('ALTER TABLE promotion_items DROP CONSTRAINT promotion_items_discount_percent_check');
        DB::statement('ALTER TABLE promotion_items DROP CONSTRAINT promotion_items_sold_within_stock_check');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_rating_range_check');
        DB::statement('ALTER TABLE inventories DROP CONSTRAINT inventories_reserved_within_quantity_check');
        DB::statement('ALTER TABLE books DROP CONSTRAINT books_prices');
    }
};
