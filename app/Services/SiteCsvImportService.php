<?php

namespace App\Services;

use App\Imports\SiteMasterDataImport;
use App\Models\MachineType;
use App\Models\Site;
use App\Models\SiteType;

class SiteCsvImportService
{
    public function __construct(private readonly SiteMasterDataImport $reader = new SiteMasterDataImport) {}

    /**
     * Imports a site master data CSV file scoped to a project.
     *
     * @return array{created: int, updated: int, errors: array<int, string>}
     */
    public function import(string $filePath, int $projectId): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        $rows = $this->reader->readRows($filePath);

        $siteTypes = SiteType::query()->pluck('id', 'name');
        $machineTypes = MachineType::query()->pluck('id', 'name');

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for header, +1 for 1-based.

            if (($row['site_code'] ?? '') === '') {
                $errors[] = "Row {$rowNumber}: site_code is required.";

                continue;
            }

            if (($row['location_name'] ?? '') === '') {
                $errors[] = "Row {$rowNumber}: location_name is required.";

                continue;
            }

            $siteTypeId = null;
            if (($row['site_type'] ?? '') !== '') {
                $siteTypeId = $siteTypes[$row['site_type']] ?? null;
                if ($siteTypeId === null) {
                    $errors[] = "Row {$rowNumber}: unknown site_type '{$row['site_type']}'. Available: ".$siteTypes->keys()->join(', ').'.';

                    continue;
                }
            }

            $machineTypeId = null;
            if (($row['machine_type'] ?? '') !== '') {
                $machineTypeId = $machineTypes[$row['machine_type']] ?? null;
                if ($machineTypeId === null) {
                    $errors[] = "Row {$rowNumber}: unknown machine_type '{$row['machine_type']}'. Available: ".$machineTypes->keys()->join(', ').'.';

                    continue;
                }
            }

            $attributes = [
                'project_id' => $projectId,
                'location_name' => $row['location_name'],
                'address' => $row['address'] ?: null,
                'province' => $row['province'] ?: null,
                'city' => $row['city'] ?: null,
                'google_map_url' => $row['google_map_url'] ?: null,
                'latitude' => $row['latitude'] !== '' ? $row['latitude'] : null,
                'longitude' => $row['longitude'] !== '' ? $row['longitude'] : null,
                'site_type_id' => $siteTypeId,
                'machine_type_id' => $machineTypeId,
                'bd_pic' => $row['bd_pic'] ?: null,
                'ss_wo_number' => $row['ss_wo_number'] ?: null,
                'cable_length_to_panel' => $row['cable_length_to_panel'] !== '' ? $row['cable_length_to_panel'] : null,
                'charging_station_count' => $row['charging_station_count'] !== '' ? (int) $row['charging_station_count'] : null,
                'power_kva' => $row['power_kva'] ?: null,
            ];

            $existing = Site::query()->where('site_code', $row['site_code'])->first();

            if ($existing !== null && $existing->project_id !== $projectId) {
                $errors[] = "Row {$rowNumber}: site_code '{$row['site_code']}' belongs to a different project.";

                continue;
            }

            if ($existing !== null) {
                $existing->fill($attributes);
                $existing->save();
                $updated++;
            } else {
                Site::query()->create([...$attributes, 'site_code' => $row['site_code']]);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }
}
