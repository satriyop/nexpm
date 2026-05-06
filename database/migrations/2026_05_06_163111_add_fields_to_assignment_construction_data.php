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
        Schema::table('assignment_construction_data', function (Blueprint $table) {
            $table->string('foto_machine_sn')->nullable()->after('machine_serial_number');
            $table->date('go_live_date_pln')->nullable()->after('catatan_progres');
            $table->date('go_live_date_pln_pass')->nullable()->after('go_live_date_pln');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_construction_data', function (Blueprint $table) {
            $table->dropColumn(['foto_machine_sn', 'go_live_date_pln', 'go_live_date_pln_pass']);
        });
    }
};
