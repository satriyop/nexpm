<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name');
            $table->string('token_prefix', 16)->nullable();
            $table->unsignedBigInteger('acting_user_id')->nullable();
            $table->string('status')->default('success'); // success / error
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('request_summary')->nullable(); // truncated params
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('tool_name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
