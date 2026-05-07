<?php

namespace Database\Seeders;

use App\Models\MachineType;
use App\Models\SiteType;
use Illuminate\Database\Seeder;

class ConfigTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['EVCS', 'BSS'] as $name) {
            SiteType::firstOrCreate(['name' => $name]);
        }

        foreach (['BSS 12S 1P', 'BSS 6S 1P', 'EVCS 20KW', 'EVCS 30KW', 'EVCS 60KW'] as $name) {
            MachineType::firstOrCreate(['name' => $name]);
        }
    }
}
