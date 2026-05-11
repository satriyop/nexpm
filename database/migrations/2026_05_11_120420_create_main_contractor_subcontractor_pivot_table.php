<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('main_contractor_subcontractor', function (Blueprint $table) {
            $table->foreignId('main_contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->primary(['main_contractor_id', 'subcontractor_id']);
        });

        Schema::table('subcontractors', function (Blueprint $table) {
            $table->dropForeign(['main_contractor_id']);
        });

        foreach (DB::table('subcontractors')->select(['id', 'main_contractor_id'])->get() as $subcontractor) {
            DB::table('main_contractor_subcontractor')->insert([
                'main_contractor_id' => $subcontractor->main_contractor_id,
                'subcontractor_id' => $subcontractor->id,
            ]);
        }

        Schema::table('subcontractors', function (Blueprint $table) {
            $table->dropColumn('main_contractor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subcontractors', function (Blueprint $table) {
            $table->foreignId('main_contractor_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (DB::table('main_contractor_subcontractor')->select(['main_contractor_id', 'subcontractor_id'])->get() as $link) {
            DB::table('subcontractors')
                ->where('id', $link->subcontractor_id)
                ->whereNull('main_contractor_id')
                ->update(['main_contractor_id' => $link->main_contractor_id]);
        }

        Schema::dropIfExists('main_contractor_subcontractor');
    }
};
