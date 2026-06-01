<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('ai_chat_messages')->cascadeOnDelete();
            $table->char('session_id', 36);
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('rating', 10);
            $table->timestamps();

            $table->index(['account_id', 'created_at'], 'ai_chat_feedback_account_created_at_index');
            $table->index(['session_id', 'created_at'], 'ai_chat_feedback_session_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_feedback');
    }
};
