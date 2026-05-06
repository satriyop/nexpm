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
        Schema::table('assignment_pln_data', function (Blueprint $table) {
            $table->date('email_bpujl_req_date')->nullable()->after('catatan_progres');
            $table->date('bpujl_acquired_date')->nullable()->after('email_bpujl_req_date');
            $table->string('foto_kwh')->nullable()->after('bpujl_acquired_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_pln_data', function (Blueprint $table) {
            $table->dropColumn(['email_bpujl_req_date', 'bpujl_acquired_date', 'foto_kwh']);
        });
    }
};
