<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 20);
            $table->string('province_code', 20)->nullable();
            $table->string('district_code', 20)->nullable();
            $table->string('ward_code', 20)->nullable();
            $table->string('detail_address');
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
