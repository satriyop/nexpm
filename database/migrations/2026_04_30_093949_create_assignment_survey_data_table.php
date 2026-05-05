<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_survey_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('surveyor_name')->nullable();
            $table->string('pic_location_name')->nullable();
            $table->string('pic_location_phone')->nullable();
            $table->string('charger_type')->nullable();
            $table->date('ss_schedule_date')->nullable();
            $table->string('cable_pulling_type')->nullable();
            $table->string('power_kva')->nullable();
            $table->string('pln_network_type')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('photo_overall_site')->nullable();
            $table->string('photo_parking_evcs')->nullable();
            $table->string('photo_other_angle')->nullable();
            $table->string('photo_pln_network')->nullable();
            $table->string('photo_satellite_gmaps')->nullable();
            $table->string('file_mockup_3d')->nullable();
            $table->string('file_ba_survey')->nullable();
            $table->string('parking_slot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_survey_data');
    }
};
