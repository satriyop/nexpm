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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machine_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('site_code')->unique();
            $table->string('location_name');
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('google_map_url')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('bd_pic')->nullable();
            $table->string('ss_wo_number')->nullable();
            $table->decimal('cable_length_to_panel', 10, 2)->nullable();
            $table->decimal('cable_length_panel_to_charger', 10, 2)->nullable();
            $table->integer('charging_station_count')->nullable();
            $table->string('ssr_url')->nullable();
            $table->string('nidi_slo_bpujl_url')->nullable();
            $table->string('sik_url')->nullable();
            $table->text('latest_remark')->nullable();
            $table->date('invoice_submission_date')->nullable();
            $table->date('dp_35_date')->nullable();
            $table->date('invoice_60_submission_date')->nullable();
            $table->date('payment_60_date')->nullable();
            $table->date('invoice_5_submission_date')->nullable();
            $table->date('payment_5_date')->nullable();
            $table->string('invoice_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
