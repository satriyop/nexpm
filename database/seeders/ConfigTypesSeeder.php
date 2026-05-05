<?php

namespace Database\Seeders;

use App\Models\MachineType;
use App\Models\SiteType;
use App\Models\SubcontractorType;
use Illuminate\Database\Seeder;

class ConfigTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Construction', 'PLN'] as $name) {
            SubcontractorType::firstOrCreate(['name' => $name]);
        }

        foreach (['EVCS', 'BSS'] as $name) {
            SiteType::firstOrCreate(['name' => $name]);
        }

        foreach (['7.7kVA', '22kVA', '50kVA'] as $name) {
            MachineType::firstOrCreate(['name' => $name]);
        }
    }
}
