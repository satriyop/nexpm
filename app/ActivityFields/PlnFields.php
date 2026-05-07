<?php

namespace App\ActivityFields;

class PlnFields
{
    public static function all(): array
    {
        return [
            [
                'key' => 'pln_status', 'label' => 'PLN Status',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 10,
                'reports' => ['daily'],
            ],
            [
                'key' => 'nidi_slo_date_acquired', 'label' => 'NIDI SLO Date Acquired',
                'type' => 'date', 'required' => false,
                'section' => 'general', 'reportable' => true, 'report_order' => 20,
                'reports' => ['daily'],
            ],
            [
                'key' => 'type_rate', 'label' => 'Type Rate',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 30,
                'reports' => ['daily'],
            ],
            [
                'key' => 'kwh_meter_installation_date', 'label' => 'kWh Meter Installation Date',
                'type' => 'date', 'required' => false,
                'section' => 'general', 'reportable' => true, 'report_order' => 40,
                'reports' => ['daily'],
            ],
            [
                'key' => 'id_pelanggan', 'label' => 'ID Pelanggan (ID PLN)',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 50,
                'reports' => ['daily'],
            ],
            [
                'key' => 'catatan_progres', 'label' => 'Catatan Progres',
                'type' => 'textarea', 'required' => false,
                'section' => 'general', 'reportable' => false, 'report_order' => 60,
                'reports' => [],
            ],
            // ── Documents ─────────────────────────────────────────────────────
            [
                'key' => 'file_slo', 'label' => 'File SLO',
                'type' => 'file', 'required' => false, 'max' => 20480,
                'section' => 'documents', 'reportable' => false, 'report_order' => 100,
                'reports' => [],
            ],
            [
                'key' => 'file_nidi', 'label' => 'File NIDI',
                'type' => 'file', 'required' => false, 'max' => 20480,
                'section' => 'documents', 'reportable' => false, 'report_order' => 110,
                'reports' => [],
            ],
            [
                'key' => 'file_reg', 'label' => 'File Reg',
                'type' => 'file', 'required' => false, 'max' => 20480,
                'section' => 'documents', 'reportable' => false, 'report_order' => 120,
                'reports' => [],
            ],
            [
                'key' => 'file_pk', 'label' => 'File PK',
                'type' => 'file', 'required' => false, 'max' => 20480,
                'section' => 'documents', 'reportable' => false, 'report_order' => 130,
                'reports' => [],
            ],
        ];
    }

    /** @return array<string, array<string>> */
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

    /** @return array<int, array<string, mixed>> */
    public static function forReport(string $reportType): array
    {
        return collect(static::all())
            ->filter(fn (array $f) => $f['reportable'] && in_array($reportType, $f['reports'], true))
            ->sortBy('report_order')
            ->values()
            ->all();
    }
}
