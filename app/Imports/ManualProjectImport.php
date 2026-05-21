<?php

namespace App\Imports;

/**
 * Column definitions for the Manual Project Import CSV template.
 */
class ManualProjectImport
{
    /**
     * All expected columns in the CSV (in order).
     *
     * @var list<string>
     */
    public const COLUMNS = [
        // Project
        'project_name',
        'client_name',
        'main_contractor_name',
        'project_start_date',
        'project_end_date',

        // Site
        'site_code',
        'location_name',
        'address',
        'province',
        'city',
        'google_map_url',
        'site_type',
        'machine_type',
        'power_kva',
        'bd_pic',
        'ss_wo_number',
        'cable_length_to_panel',
        'charging_station_count',

        // Assignment
        'activity_type',
        'subcontractor_code',
        'status',

        // Survey data (activity_type = SURVEY)
        'ss_schedule_date',
        'ss_report_submission_date',
        'cable_pulling_type',
        'parking_slot',

        // Construction data (activity_type = CONSTRUCTION)
        'cons_wo_number',
        'setup_approval_date',
        'cons_actual_start_date',
        'cons_actual_done_date',
        'machine_serial_number',
        'go_live_date_pln',
        'go_live_date_pln_pass',

        // PLN data (activity_type = PLN_CONNECTION)
        'kwh_meter_installation_date',
        'type_rate',
        'id_pelanggan',
        'nidi_slo_date_acquired',
        'bpujl_acquired_date',

        // BAST data (activity_type = BAST)
        'sim_provider',
        'nomor_simcard',
    ];

    /**
     * Read a CSV file and return data rows as associative arrays.
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
            // Detect delimiter — skip comment rows to find the actual header.
            $separator = ',';
            while (($headerLine = fgets($handle)) !== false) {
                $trimmed = ltrim($headerLine);
                if (! str_starts_with($trimmed, '#')) {
                    $separator = str_contains($headerLine, ';') ? ';' : ',';
                    break;
                }
            }

            while (($data = fgetcsv($handle, separator: $separator, escape: '\\')) !== false) {
                if ($data === [null] || $this->isEmpty($data)) {
                    continue;
                }

                // Skip comment rows.
                $firstCell = trim((string) ($data[0] ?? ''));
                if (str_starts_with($firstCell, '#')) {
                    continue;
                }

                $row = [];
                foreach (self::COLUMNS as $idx => $column) {
                    $row[$column] = isset($data[$idx]) ? $this->toUtf8(trim((string) $data[$idx])) : '';
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
    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

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
