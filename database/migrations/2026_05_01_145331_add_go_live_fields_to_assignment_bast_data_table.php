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
        Schema::table('assignment_bast_data', function (Blueprint $table) {
            $table->string('nomor_simcard')->nullable()->after('measurements');
            $table->date('go_live_date_pln_pass')->nullable()->after('nomor_simcard');
            $table->date('go_live_date_pln')->nullable()->after('go_live_date_pln_pass');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_bast_data', function (Blueprint $table) {
            $table->dropColumn(['nomor_simcard', 'go_live_date_pln_pass', 'go_live_date_pln']);
        });
    }
};
