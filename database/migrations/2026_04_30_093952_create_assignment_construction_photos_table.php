<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_construction_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_construction_data_id')
                ->constrained('assignment_construction_data')
                ->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_construction_photos');
    }
};
