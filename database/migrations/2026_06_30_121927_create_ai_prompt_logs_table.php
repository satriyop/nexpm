<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_prompt_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome');
            $table->string('tool_name')->nullable();
            $table->text('prompt');
            $table->json('context')->nullable();
            $table->json('filters')->nullable();
            $table->json('matched_entities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['outcome', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_logs');
    }
};
