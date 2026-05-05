<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontractor_types', function (Blueprint $table) {
            $table->json('activity_types')->nullable()->after('name');
        });

        // Seed defaults for existing records based on conventional names.
        DB::table('subcontractor_types')
            ->where('name', 'like', '%Construction%')
            ->update(['activity_types' => json_encode(['SURVEY', 'CONSTRUCTION', 'BAST'])]);

        DB::table('subcontractor_types')
            ->where('name', 'like', '%PLN%')
            ->update(['activity_types' => json_encode(['PLN_CONNECTION'])]);
    }

    public function down(): void
    {
        Schema::table('subcontractor_types', function (Blueprint $table) {
            $table->dropColumn('activity_types');
        });
    }
};
