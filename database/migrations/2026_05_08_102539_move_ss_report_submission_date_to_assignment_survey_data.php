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
        Schema::table('assignment_survey_data', function (Blueprint $table) {
            $table->date('ss_report_submission_date')->nullable()->after('parking_slot');
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('ss_report_submission_date');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_survey_data', function (Blueprint $table) {
            $table->dropColumn('ss_report_submission_date');
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->date('ss_report_submission_date')->nullable();
        });
    }
};
