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
            $table->string('file_site_plan')->nullable()->after('file_mockup_3d');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_survey_data', function (Blueprint $table) {
            $table->dropColumn('file_site_plan');
        });
    }
};
