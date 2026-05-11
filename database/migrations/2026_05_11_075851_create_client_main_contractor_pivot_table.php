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
        // Create pivot table
        Schema::create('client_main_contractor', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('main_contractor_id')->constrained()->cascadeOnDelete();
            $table->primary(['client_id', 'main_contractor_id']);
        });

        // Drop old single-column foreign key
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['main_contractor_id']);
            $table->dropColumn('main_contractor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original column
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('main_contractor_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('client_main_contractor');
    }
};
