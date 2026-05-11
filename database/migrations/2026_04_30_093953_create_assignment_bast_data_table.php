<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_bast_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plant_name')->nullable();
            $table->text('plant_address')->nullable();
            $table->string('plant_coordinate')->nullable();
            $table->string('gmaps_link')->nullable();
            $table->string('charger_type')->nullable();
            $table->string('sn_unit')->nullable();
            $table->string('id_pln')->nullable();
            $table->string('sim_provider')->nullable();
            $table->string('installation_vendor')->nullable();
            $table->string('pic_vendor_contact')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('commissioning_date')->nullable();
            $table->string('customer')->nullable();
            $table->json('measurements')->nullable();
            $table->string('nomor_simcard')->nullable();
            $table->date('go_live_date_pln_pass')->nullable();
            $table->date('go_live_date_pln')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_bast_data');
    }
};
