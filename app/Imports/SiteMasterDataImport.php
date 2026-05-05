<?php

namespace App\Imports;

/**
 * Lightweight CSV reader for Site master data.
 *
 * Returns rows as associative arrays keyed by column header.
 * The actual upsert logic lives in App\Services\SiteCsvImportService.
 */
class SiteMasterDataImport
{
    /**
     * Expected header columns in the CSV (in order).
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'site_code',
        'location_name',
        'address',
        'province',
        'city',
        'google_map_url',
        'latitude',
        'longitude',
        'site_type',
        'machine_type',
        'bd_pic',
        'ss_wo_number',
        'cable_length_to_panel',
        'charging_station_count',
    ];

    /**
     * Read a CSV file and return the data rows (skipping the header).
     *
     * @return array<int, array<string, string>>
     */
    public function readRows(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return [];
        }

        try {
            // Skip the header row.
            fgetcsv($handle, escape: '\\');

            while (($data = fgetcsv($handle, escape: '\\')) !== false) {
                if ($data === [null] || $this->isEmpty($data)) {
                    continue;
                }

                $row = [];
                foreach (self::COLUMNS as $idx => $column) {
                    $row[$column] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
                }
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
