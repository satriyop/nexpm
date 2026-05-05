<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_construction_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('cons_wo_number')->nullable();
            $table->string('project_status')->nullable();
            $table->date('setup_approval_date')->nullable();
            $table->date('cons_actual_start_date')->nullable();
            $table->date('cons_actual_done_date')->nullable();
            $table->string('machine_serial_number')->nullable();
            $table->text('catatan_progres')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_construction_data');
    }
};
