<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Nullify any sites referencing the old machine types before deleting them.
        DB::table('sites')
            ->whereIn('machine_type_id', DB::table('machine_types')->whereIn('name', ['7.7kVA', '22kVA', '50kVA'])->pluck('id'))
            ->update(['machine_type_id' => null]);

        DB::table('machine_types')->whereIn('name', ['7.7kVA', '22kVA', '50kVA'])->delete();

        foreach (['BSS 12S 1P', 'BSS 6S 1P', 'EVCS 20KW', 'EVCS 30KW', 'EVCS 60KW'] as $name) {
            DB::table('machine_types')->insertOrIgnore(['name' => $name]);
        }
    }

    public function down(): void
    {
        DB::table('machine_types')->whereIn('name', ['BSS 12S 1P', 'BSS 6S 1P', 'EVCS 20KW', 'EVCS 30KW', 'EVCS 60KW'])->delete();

        foreach (['7.7kVA', '22kVA', '50kVA'] as $name) {
            DB::table('machine_types')->insertOrIgnore(['name' => $name]);
        }
    }
};
