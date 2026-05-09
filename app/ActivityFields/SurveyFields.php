<?php

namespace App\ActivityFields;

/**
 * Single source of truth for Survey activity field definitions.
 *
 * 'reports' values:
 *   'ssr'         → included in SSR auto-generated columns
 *   'daily_extra' → appended to Daily Monitoring as extra columns (for NEW fields)
 *
 * Existing daily columns (ss_schedule_date, cable_pulling_type, power_kva, parking_slot)
 * are already hardcoded in buildDailySheet and should NOT use 'daily_extra' to avoid
 * duplicates — they use 'ssr' only.
 */
class SurveyFields
{
    public static function all(): array
    {
        return [
            // ── General ───────────────────────────────────────────────────────
            [
                'key' => 'surveyor_name', 'label' => 'Surveyor Name', 'report_label' => 'Surveyor',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 10,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'pic_location_name', 'label' => 'PIC Location Name', 'report_label' => 'PIC Location',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 20,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'pic_location_phone', 'label' => 'PIC Location Phone', 'report_label' => 'PIC Phone',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 30,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'charger_type', 'label' => 'Charger Type',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 40,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'ss_schedule_date', 'label' => 'SS Schedule Date',
                'type' => 'date', 'required' => false,
                'section' => 'general', 'reportable' => true, 'report_order' => 50,
                'reports' => ['ssr'],
            ],
            // ── Technical ─────────────────────────────────────────────────────
            [
                'key' => 'cable_pulling_type', 'label' => 'Cable Pulling Type',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'technical', 'reportable' => true, 'report_order' => 60,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'pln_network_type', 'label' => 'PLN Network Type',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'technical', 'reportable' => true, 'report_order' => 70,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'power_kva', 'label' => 'Power (kVA)',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'technical', 'reportable' => true, 'report_order' => 75,
                'reports' => [],
            ],
            [
                'key' => 'additional_info', 'label' => 'Additional Info',
                'type' => 'textarea', 'required' => false,
                'section' => 'technical', 'reportable' => true, 'report_order' => 80,
                'reports' => ['ssr'],
            ],
            // ── Photos ────────────────────────────────────────────────────────
            [
                'key' => 'photo_overall_site', 'label' => 'Foto Tampak Keseluruhan Site',
                'type' => 'image', 'required' => false, 'max' => 10240,
                'section' => 'photos', 'reportable' => true, 'report_order' => 200,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'photo_parking_evcs', 'label' => 'Foto Lahan Parkir EVCS / Lokasi BSS',
                'type' => 'image', 'required' => false, 'max' => 10240,
                'section' => 'photos', 'reportable' => true, 'report_order' => 210,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'photo_access_route', 'label' => 'Foto Jalur Akses Menuju Lokasi',
                'type' => 'image', 'required' => false, 'max' => 10240,
                'section' => 'photos', 'reportable' => true, 'report_order' => 220,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'photo_pln_network', 'label' => 'Foto Jaringan PLN Terdekat',
                'type' => 'image', 'required' => false, 'max' => 10240,
                'section' => 'photos', 'reportable' => true, 'report_order' => 230,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'photo_satellite_gmaps', 'label' => 'Foto Satelit GMaps',
                'type' => 'image', 'required' => false, 'max' => 10240,
                'section' => 'photos', 'reportable' => true, 'report_order' => 240,
                'reports' => ['ssr'],
            ],
            // ── Documents ─────────────────────────────────────────────────────
            [
                'key' => 'file_mockup_3d', 'label' => 'Mock Up 3D',
                'type' => 'file', 'required' => false, 'max' => 20480,
                'section' => 'documents', 'reportable' => true, 'report_order' => 300,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'parking_slot', 'label' => 'Parking Slot',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 305,
                'reports' => ['ssr'],
            ],
            [
                'key' => 'file_ba_survey', 'label' => 'BA Survey',
                'type' => 'file', 'required' => false, 'max' => 20480,
                'section' => 'documents', 'reportable' => true, 'report_order' => 310,
                'reports' => ['ssr'],
            ],
        ];
    }

    /**
     * Returns Laravel validation rules keyed by field name.
     *
     * @return array<string, array<string>>
     */
    public static function validationRules(): array
    {
        return collect(static::all())
            ->mapWithKeys(fn (array $f) => [
                $f['key'] => match ($f['type']) {
                    'image' => [$f['required'] ? 'required' : 'nullable', 'file', 'image', 'max:'.($f['max'] ?? 10240)],
                    'file' => [$f['required'] ? 'required' : 'nullable', 'file', 'max:'.($f['max'] ?? 20480)],
                    'date' => [$f['required'] ? 'required' : 'nullable', 'date'],
                    'number' => [$f['required'] ? 'required' : 'nullable', 'numeric'],
                    'textarea' => [$f['required'] ? 'required' : 'nullable', 'string'],
                    default => [$f['required'] ? 'required' : 'nullable', 'string', 'max:'.($f['max'] ?? 255)],
                },
            ])
            ->all();
    }

    /**
     * Returns fields included in a given report type, sorted by report_order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forReport(string $reportType): array
    {
        return collect(static::all())
            ->filter(fn (array $f) => $f['reportable'] && in_array($reportType, $f['reports'], true))
            ->sortBy('report_order')
            ->values()
            ->all();
    }
}
