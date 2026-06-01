<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('ai_chat_messages')->cascadeOnDelete();
            $table->decimal('groundedness_score', 4, 3);
            $table->decimal('relevance_score', 4, 3);
            $table->boolean('has_hallucination_risk');
            $table->string('verdict', 20);
            $table->json('risk_flags')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->unique('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_evaluations');
    }
};
