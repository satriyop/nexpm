<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_survey_data', function (Blueprint $table) {
            $table->renameColumn('photo_other_angle', 'photo_access_route');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_survey_data', function (Blueprint $table) {
            $table->renameColumn('photo_access_route', 'photo_other_angle');
        });
    }
};
