<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_details', function (Blueprint $table) {
            $table->foreignId('book_id')->primary()->constrained('books')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('language', 50);
            $table->string('translator', 50)->nullable();
            $table->smallInteger('publication_year')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('dimensions', 50)->nullable();
            $table->unsignedInteger('num_pages')->nullable();
            $table->string('format', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_details');
    }
};
