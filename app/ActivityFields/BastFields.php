<?php

namespace App\ActivityFields;

class BastFields
{
    public static function all(): array
    {
        return [
            // ── Cover Sheet ────────────────────────────────────────────────
            [
                'key' => 'plant_name', 'label' => 'Plant Name',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 10,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D5']],
            ],
            [
                'key' => 'plant_address', 'label' => 'Plant Address',
                'type' => 'textarea', 'required' => false,
                'section' => 'cover', 'reportable' => false, 'report_order' => 20,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D6']],
            ],
            [
                'key' => 'plant_coordinate', 'label' => 'Plant Coordinate',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 30,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D7']],
            ],
            [
                'key' => 'gmaps_link', 'label' => 'GMaps Link',
                'type' => 'text', 'required' => false, 'max' => 500,
                'section' => 'cover', 'reportable' => false, 'report_order' => 40,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D8']],
            ],
            [
                'key' => 'charger_type', 'label' => 'Charger Type',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 50,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D9']],
            ],
            [
                'key' => 'sn_unit', 'label' => 'SN Unit',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 60,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D10']],
            ],
            [
                'key' => 'id_pln', 'label' => 'ID PLN',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 70,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D11']],
            ],
            [
                'key' => 'sim_provider', 'label' => 'SIM Provider',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 80,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D12']],
            ],
            [
                'key' => 'installation_vendor', 'label' => 'Installation Vendor',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 90,
                'reports' => [],
                // Fills both the vendor row and the header vendor cell
                'bast_cells' => ['Cover' => ['D13', 'G3']],
            ],
            [
                'key' => 'pic_vendor_contact', 'label' => 'PIC Vendor Contact',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 100,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D14']],
            ],
            [
                'key' => 'installation_date', 'label' => 'Installation Date',
                'type' => 'date', 'required' => false,
                'section' => 'cover', 'reportable' => false, 'report_order' => 110,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D15']],
            ],
            [
                'key' => 'commissioning_date', 'label' => 'Commissioning Date',
                'type' => 'date', 'required' => false,
                'section' => 'cover', 'reportable' => false, 'report_order' => 120,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D16']],
            ],
            [
                'key' => 'customer', 'label' => 'Customer',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 130,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D17']],
            ],
            // ── Extra fields (no template cell — go to supplemental sheet) ──
            [
                'key' => 'nomor_simcard', 'label' => 'Nomor SIM Card',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 140,
                'reports' => [],
                'bast_cells' => [],
            ],
            [
                'key' => 'go_live_date_pln_pass', 'label' => 'Go Live Date (PLN Pass)',
                'type' => 'date', 'required' => false,
                'section' => 'cover', 'reportable' => false, 'report_order' => 150,
                'reports' => [],
                'bast_cells' => [],
            ],
            [
                'key' => 'go_live_date_pln', 'label' => 'Go Live Date (PLN)',
                'type' => 'date', 'required' => false,
                'section' => 'cover', 'reportable' => false, 'report_order' => 160,
                'reports' => [],
                'bast_cells' => [],
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

    /** Fields with explicit Excel template cell assignments. */
    public static function templateFields(): array
    {
        return collect(static::all())
            ->filter(fn (array $f) => ! empty($f['bast_cells']))
            ->values()
            ->all();
    }

    /** Fields with no template cell — auto-appended to the supplemental sheet. */
    public static function supplementalFields(): array
    {
        return collect(static::all())
            ->filter(fn (array $f) => empty($f['bast_cells']))
            ->values()
            ->all();
    }
}
