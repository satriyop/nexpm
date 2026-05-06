<?php

namespace App\Services;

use App\ActivityFields\BastFields;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class BastReportExportService
{
    /**
     * Maps checkpoint_key => [sheet, column, row] for image anchoring.
     * Column is the starting column letter for the photo area (B or F).
     *
     * @var array<string, array{string, string, int}>
     */
    private const EVCS_CHECKPOINT_MAP = [
        // EV Charger sheet
        'device_front_view_open' => ['EV Charger', 'B', 3],
        'device_side_view_right' => ['EV Charger', 'F', 3],
        'device_front_view_close' => ['EV Charger', 'B', 4],
        'device_side_view_left' => ['EV Charger', 'F', 4],
        'device_foundation_depth' => ['EV Charger', 'B', 5],
        'device_foundation_concrete' => ['EV Charger', 'F', 5],
        'device_parking_space' => ['EV Charger', 'B', 6],
        'device_sticker' => ['EV Charger', 'F', 6],
        'device_name_plate' => ['EV Charger', 'B', 7],
        'device_ac_cable_termination' => ['EV Charger', 'F', 7],
        'device_emergency_button_cover' => ['EV Charger', 'B', 8],
        'device_grounding_termination' => ['EV Charger', 'F', 8],
        'device_cable_entry_panel' => ['EV Charger', 'B', 9],
        'device_visible_safety_sign' => ['EV Charger', 'F', 9],
        'sim_kartu_perdana' => ['EV Charger', 'B', 12],
        'sim_installed_sim_card' => ['EV Charger', 'F', 12],
        'sim_signal_bar' => ['EV Charger', 'B', 13],
        'sim_url' => ['EV Charger', 'F', 13],
        'sim_start_charge_immediately' => ['EV Charger', 'B', 14],
        'sim_user_interface_lcd' => ['EV Charger', 'F', 14],
        // Grounding sheet
        'grounding_rod_connection' => ['Grounding', 'B', 3],
        'grounding_connected_to_device' => ['Grounding', 'F', 3],
        'grounding_rod_to_earth_1' => ['Grounding', 'B', 4],
        'grounding_busbar_panel' => ['Grounding', 'F', 4],
        'grounding_cable_route' => ['Grounding', 'B', 5],
        // Fire extinguisher (EVCS only)
        'fire_ext_front_view' => ['Grounding', 'B', 10],
        'fire_ext_pressure' => ['Grounding', 'F', 10],
        'fire_ext_placement' => ['Grounding', 'B', 11],
        'fire_ext_specification' => ['Grounding', 'F', 11],
        // KWH, AC Panel, Cable sheet
        'kwh_kwh_meter' => ['KWH,AC Panel, Cable', 'B', 4],
        'kwh_mcb_pln' => ['KWH,AC Panel, Cable', 'F', 4],
        'ac_front_view_open' => ['KWH,AC Panel, Cable', 'B', 8],
        'ac_safety_sign' => ['KWH,AC Panel, Cable', 'F', 8],
        'ac_front_view_close' => ['KWH,AC Panel, Cable', 'B', 9],
        'ac_pilot_lamps' => ['KWH,AC Panel, Cable', 'F', 9],
        'ac_side_view_right' => ['KWH,AC Panel, Cable', 'B', 10],
        'ac_locking_system' => ['KWH,AC Panel, Cable', 'F', 10],
        'ac_side_view_left' => ['KWH,AC Panel, Cable', 'B', 11],
        'ac_mcb' => ['KWH,AC Panel, Cable', 'F', 11],
        'ac_panel_wiring' => ['KWH,AC Panel, Cable', 'B', 12],
        'ac_phase_marking' => ['KWH,AC Panel, Cable', 'F', 12],
        'cable_spec' => ['KWH,AC Panel, Cable', 'B', 23],
        'cable_routing_1' => ['KWH,AC Panel, Cable', 'F', 23],
        'cable_routing_2' => ['KWH,AC Panel, Cable', 'B', 24],
        'cable_routing_3' => ['KWH,AC Panel, Cable', 'F', 24],
    ];

    private const BSS_CHECKPOINT_MAP = [
        // BSS sheet
        'device_front_view_open' => ['BSS', 'B', 3],
        'device_side_view_right' => ['BSS', 'F', 3],
        'device_front_view_close' => ['BSS', 'B', 4],
        'device_side_view_left' => ['BSS', 'F', 4],
        'device_foundation_depth' => ['BSS', 'B', 5],
        'device_foundation_concrete' => ['BSS', 'F', 5],
        'device_sticker' => ['BSS', 'B', 6],
        'device_name_plate' => ['BSS', 'F', 6],
        'device_ac_cable_termination' => ['BSS', 'B', 7],
        'device_grounding_termination' => ['BSS', 'F', 7],
        'device_cable_entry_panel' => ['BSS', 'B', 8],
        'device_visible_safety_sign' => ['BSS', 'F', 8],
        'sim_kartu_perdana' => ['BSS', 'B', 11],
        'sim_installed_sim_card' => ['BSS', 'F', 11],
        'sim_signal_bar' => ['BSS', 'B', 12],
        'sim_url' => ['BSS', 'F', 12],
        'sim_user_interface_lcd' => ['BSS', 'B', 13],
        // Grounding sheet (same as EVCS)
        'grounding_rod_connection' => ['Grounding', 'B', 3],
        'grounding_connected_to_device' => ['Grounding', 'F', 3],
        'grounding_rod_to_earth_1' => ['Grounding', 'B', 4],
        'grounding_busbar_panel' => ['Grounding', 'F', 4],
        'grounding_cable_route' => ['Grounding', 'B', 5],
        // KWH, AC Panel, Cable (same layout as EVCS)
        'kwh_kwh_meter' => ['KWH,AC Panel, Cable', 'B', 4],
        'kwh_mcb_pln' => ['KWH,AC Panel, Cable', 'F', 4],
        'ac_front_view_open' => ['KWH,AC Panel, Cable', 'B', 8],
        'ac_safety_sign' => ['KWH,AC Panel, Cable', 'F', 8],
        'ac_front_view_close' => ['KWH,AC Panel, Cable', 'B', 9],
        'ac_pilot_lamps' => ['KWH,AC Panel, Cable', 'F', 9],
        'ac_side_view_right' => ['KWH,AC Panel, Cable', 'B', 10],
        'ac_locking_system' => ['KWH,AC Panel, Cable', 'F', 10],
        'ac_side_view_left' => ['KWH,AC Panel, Cable', 'B', 11],
        'ac_mcb' => ['KWH,AC Panel, Cable', 'F', 11],
        'ac_panel_wiring' => ['KWH,AC Panel, Cable', 'B', 12],
        'ac_phase_marking' => ['KWH,AC Panel, Cable', 'F', 12],
        'cable_spec' => ['KWH,AC Panel, Cable', 'B', 23],
        'cable_routing_1' => ['KWH,AC Panel, Cable', 'F', 23],
        'cable_routing_2' => ['KWH,AC Panel, Cable', 'B', 24],
        'cable_routing_3' => ['KWH,AC Panel, Cable', 'F', 24],
    ];

    /** Cell where grounding resistance Ω value is written. */
    private const GROUNDING_OHM_CELL = 'F6';

    /** Measurement cells: [sheet, cell] keyed by measurement key. */
    private const EVCS_MEASUREMENT_CELLS = [
        'voltage_rs' => ['KWH,AC Panel, Cable', 'B15'],
        'voltage_rt' => ['KWH,AC Panel, Cable', 'F15'],
        'voltage_st' => ['KWH,AC Panel, Cable', 'B17'],
        'frequency' => ['KWH,AC Panel, Cable', 'F17'],
        'voltage_ng' => ['KWH,AC Panel, Cable', 'B19'],
    ];

    private const BSS_MEASUREMENT_CELLS = [
        'voltage_rn' => ['KWH,AC Panel, Cable', 'B15'],
        'voltage_rg' => ['KWH,AC Panel, Cable', 'F15'],
        'frequency' => ['KWH,AC Panel, Cable', 'B17'],
        'voltage_ng' => ['KWH,AC Panel, Cable', 'F17'],
    ];

    public function generate(Assignment $assignment): Spreadsheet
    {
        $assignment->loadMissing(['site.siteType', 'bastData.bastPhotos']);

        $isBss = strtoupper($assignment->site->siteType?->name ?? 'EVCS') === 'BSS';
        $templatePath = $isBss
            ? base_path('docs/COMM TEST REPORT V1.1 BSS.xlsx')
            : base_path('docs/EVCS COMM TEST REPORT V1.3.xlsx');

        $spreadsheet = IOFactory::load($templatePath);

        $bastData = $assignment->bastData;

        $this->fillCoverSheet($spreadsheet, $bastData, $assignment);
        $this->fillMeasurements($spreadsheet, $bastData, $isBss);
        $this->embedPhotos($spreadsheet, $bastData, $isBss);
        $this->fillSupplementalFields($spreadsheet, $bastData);

        return $spreadsheet;
    }

    private function fillCoverSheet(Spreadsheet $spreadsheet, ?AssignmentBastData $bastData, Assignment $assignment): void
    {
        $sheet = $spreadsheet->getSheetByName('Cover');

        if ($sheet === null || $bastData === null) {
            return;
        }

        $values = [
            'D5' => $bastData->plant_name,
            'D6' => $bastData->plant_address,
            'D7' => $bastData->plant_coordinate,
            'D8' => $bastData->gmaps_link,
            'D9' => $bastData->charger_type,
            'D10' => $bastData->sn_unit,
            'D11' => $bastData->id_pln,
            'D12' => $bastData->sim_provider,
            'D13' => $bastData->installation_vendor,
            'D14' => $bastData->pic_vendor_contact,
            'D15' => $bastData->installation_date?->format('d/m/Y'),
            'D16' => $bastData->commissioning_date?->format('d/m/Y'),
            'D17' => $bastData->customer,
            // Vendor column (F3) — filled from installation_vendor
            'G3' => $bastData->installation_vendor,
        ];

        foreach ($values as $coordinate => $value) {
            $sheet->setCellValue($coordinate, $value ?? '');
        }
    }

    private function fillMeasurements(Spreadsheet $spreadsheet, ?AssignmentBastData $bastData, bool $isBss): void
    {
        if ($bastData === null) {
            return;
        }

        $measurements = $bastData->measurements ?? [];
        $measurementMap = $isBss ? self::BSS_MEASUREMENT_CELLS : self::EVCS_MEASUREMENT_CELLS;

        foreach ($measurementMap as $key => [$sheetName, $cell]) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $sheet?->setCellValue($cell, $measurements[$key] ?? '');
        }

        // Grounding resistance on the Grounding sheet
        $groundingSheet = $spreadsheet->getSheetByName('Grounding');
        $groundingSheet?->setCellValue(self::GROUNDING_OHM_CELL, ($measurements['grounding_resistance'] ?? '').' Ω');
    }

    private function fillSupplementalFields(Spreadsheet $spreadsheet, ?AssignmentBastData $bastData): void
    {
        $supplementalFields = BastFields::supplementalFields();

        if (empty($supplementalFields) || $bastData === null) {
            return;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Additional Data');

        $headers = ['Field', 'Value'];
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($supplementalFields as $row => $field) {
            $rowNum = $row + 2;
            $sheet->getCellByColumnAndRow(1, $rowNum)->setValue($field['label']);
            $sheet->getCellByColumnAndRow(2, $rowNum)->setValue($bastData->{$field['key']} ?? '');
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    private function embedPhotos(Spreadsheet $spreadsheet, ?AssignmentBastData $bastData, bool $isBss): void
    {
        if ($bastData === null) {
            return;
        }

        $checkpointMap = $isBss ? self::BSS_CHECKPOINT_MAP : self::EVCS_CHECKPOINT_MAP;
        $photosByKey = $bastData->bastPhotos->keyBy('checkpoint_key');

        foreach ($checkpointMap as $checkpointKey => [$sheetName, $col, $row]) {
            $photo = $photosByKey->get($checkpointKey);

            if ($photo === null) {
                continue;
            }

            $absolutePath = storage_path('app/public/'.$photo->photo_path);

            if (! file_exists($absolutePath)) {
                continue;
            }

            $sheet = $spreadsheet->getSheetByName($sheetName);

            if ($sheet === null) {
                continue;
            }

            $drawing = new Drawing;
            $drawing->setPath($absolutePath);
            $drawing->setCoordinates($col.$row);
            $drawing->setOffsetX(2);
            $drawing->setOffsetY(2);
            // Width spans 3 columns (B-D or F-H); height fills the 140pt row
            $drawing->setWidth(280);
            $drawing->setHeight(170);
            $drawing->setWorksheet($sheet);
        }
    }
}
