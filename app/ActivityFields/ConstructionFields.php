<?php

namespace App\ActivityFields;

class ConstructionFields
{
    public static function all(): array
    {
        return [
            [
                'key' => 'cons_actual_start_date', 'label' => 'Cons Actual Start Date',
                'type' => 'date', 'required' => false,
                'section' => 'general', 'reportable' => true, 'report_order' => 10,
                'reports' => ['daily'],
            ],
            [
                'key' => 'cons_actual_done_date', 'label' => 'Cons Actual Done Date',
                'type' => 'date', 'required' => false,
                'section' => 'general', 'reportable' => true, 'report_order' => 20,
                'reports' => ['daily'],
            ],
            [
                'key' => 'machine_serial_number', 'label' => 'Machine Serial Number',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'general', 'reportable' => true, 'report_order' => 30,
                'reports' => ['daily'],
            ],
            [
                'key' => 'foto_machine_sn', 'label' => 'Foto Machine SN',
                'type' => 'image', 'required' => false, 'max' => 10240,
                'section' => 'general', 'reportable' => false, 'report_order' => 35,
                'reports' => [],
            ],
            [
                'key' => 'catatan_progres', 'label' => 'Catatan Progres',
                'type' => 'textarea', 'required' => false,
                'section' => 'general', 'reportable' => false, 'report_order' => 40,
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
