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
        Schema::create('assignment_bast_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_bast_data_id')
                ->constrained('assignment_bast_data')
                ->cascadeOnDelete();
            $table->string('section');
            $table->string('checkpoint_key');
            $table->string('photo_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_bast_photos');
    }
};
