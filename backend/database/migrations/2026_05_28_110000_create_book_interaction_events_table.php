<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_interaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('source', 30)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'event_type', 'created_at'], 'bie_account_type_created_at_index');
            $table->index(['account_id', 'book_id', 'created_at'], 'bie_account_book_created_at_index');
            $table->index(['book_id', 'event_type', 'created_at'], 'bie_book_type_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_interaction_events');
    }
};
