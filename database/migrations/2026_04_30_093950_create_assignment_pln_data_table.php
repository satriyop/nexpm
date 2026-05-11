<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_pln_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('pln_status')->nullable();
            $table->date('nidi_slo_date_acquired')->nullable();
            $table->string('type_rate')->nullable();
            $table->string('file_slo')->nullable();
            $table->string('file_nidi')->nullable();
            $table->string('file_reg')->nullable();
            $table->string('file_pk')->nullable();
            $table->date('kwh_meter_installation_date')->nullable();
            $table->string('id_pelanggan')->nullable();
            $table->text('catatan_progres')->nullable();
            $table->date('email_bpujl_req_date')->nullable();
            $table->date('bpujl_acquired_date')->nullable();
            $table->string('foto_kwh')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_pln_data');
    }
};
