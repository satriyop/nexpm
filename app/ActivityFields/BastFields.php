<?php

namespace App\ActivityFields;

class BastFields
{
    public static function all(): array
    {
        return [
            // ── Editable cover fields ──────────────────────────────────────
            [
                'key' => 'sim_provider', 'label' => 'Provider Sim Card',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 10,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D12']],
            ],
            [
                'key' => 'nomor_simcard', 'label' => 'Nomor SIM Card',
                'type' => 'text', 'required' => false, 'max' => 255,
                'section' => 'cover', 'reportable' => false, 'report_order' => 20,
                'reports' => [],
                'bast_cells' => [],
            ],
            [
                'key' => 'commissioning_date', 'label' => 'Commissioning Date',
                'type' => 'date', 'required' => false,
                'section' => 'cover', 'reportable' => false, 'report_order' => 30,
                'reports' => [],
                'bast_cells' => ['Cover' => ['D16']],
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
