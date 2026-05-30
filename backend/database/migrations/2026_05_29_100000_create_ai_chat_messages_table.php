<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->char('session_id', 36);
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->string('model_version', 100);
            $table->string('retrieval_strategy', 30);
            $table->decimal('retrieval_top_score', 6, 4)->nullable();
            $table->boolean('retrieval_matched');
            $table->json('retrieved_books')->nullable();
            $table->json('token_usage')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at'], 'ai_chat_messages_session_created_at_index');
            $table->index(['account_id', 'created_at'], 'ai_chat_messages_account_created_at_index');
            $table->index(['retrieval_matched', 'created_at'], 'ai_chat_messages_retrieval_matched_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
