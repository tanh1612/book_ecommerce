<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('public_id');
            $table->text('image_url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('public_id', 'banners_public_id_unique');
            $table->index(['is_active', 'sort_order'], 'banners_active_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
