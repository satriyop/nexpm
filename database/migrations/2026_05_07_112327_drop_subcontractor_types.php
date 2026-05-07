<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontractors', function (Blueprint $table) {
            $table->dropForeign(['subcontractor_type_id']);
            $table->dropColumn('subcontractor_type_id');
        });

        Schema::dropIfExists('subcontractor_types');
    }

    public function down(): void
    {
        Schema::create('subcontractor_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('activity_types')->nullable();
            $table->timestamps();
        });

        Schema::table('subcontractors', function (Blueprint $table) {
            $table->foreignId('subcontractor_type_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
