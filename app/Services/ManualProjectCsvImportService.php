<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Imports\ManualProjectImport;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\Client;
use App\Models\MachineType;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteType;
use App\Models\Subcontractor;
use App\Support\GoogleMapsCoordinates;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManualProjectCsvImportService
{
    public function __construct(private readonly ManualProjectImport $reader = new ManualProjectImport) {}

    /**
     * Parse the CSV and return a preview without writing to the database.
     *
     * Each item in 'rows' has: row_number, project_name, site_code, activity_type,
     * status ('ok'|'error'|'skipped'), message, data (raw CSV values).
     *
     * @return array{rows: list<array<string, mixed>>, summary: array{ok: int, errors: int, skipped: int}}
     */
    public function preview(string $filePath): array
    {
        $rawRows = $this->reader->readRows($filePath);

        [$lookups, $lookupErrors] = $this->loadLookups();
        $existingProjectKeys = $this->preloadExistingProjectKeys($rawRows);

        $rows = [];
        $ok = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($rawRows as $index => $row) {
            $rowNumber = $index + 2;

            $result = $this->validateRow($row, $rowNumber, $lookups, $existingProjectKeys);
            $result['data'] = $row;

            if ($result['status'] === 'ok') {
                $ok++;
            } elseif ($result['status'] === 'skipped') {
                $skipped++;
            } else {
                $errors++;
            }

            $rows[] = $result;
        }

        if ($lookupErrors !== []) {
            array_unshift($rows, [
                'row_number' => null,
                'project_name' => '',
                'site_code' => '',
                'activity_type' => '',
                'status' => 'error',
                'message' => 'Lookup error: '.implode('; ', $lookupErrors),
                'data' => [],
            ]);
            $errors++;
        }

        return [
            'rows' => $rows,
            'summary' => ['ok' => $ok, 'errors' => $errors, 'skipped' => $skipped],
        ];
    }

    /**
     * Parse and commit a CSV to the database. Skips error rows, commits valid rows.
     *
     * @return array{created_projects: int, created_sites: int, created_assignments: int, skipped: int, errors: list<string>}
     */
    public function import(string $filePath): array
    {
        $rawRows = $this->reader->readRows($filePath);
        [$lookups] = $this->loadLookups();
        $existingProjectKeys = $this->preloadExistingProjectKeys($rawRows);

        $createdProjects = 0;
        $createdSites = 0;
        $createdAssignments = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rawRows, $lookups, $existingProjectKeys, &$createdProjects, &$createdSites, &$createdAssignments, &$skipped, &$errors) {
            // Track created/upserted projects and sites within this transaction
            // to avoid duplicate DB lookups on subsequent rows.
            $projectCache = [];
            $siteCache = [];

            foreach ($rawRows as $index => $row) {
                $rowNumber = $index + 2;
                $result = $this->validateRow($row, $rowNumber, $lookups, $existingProjectKeys);

                if ($result['status'] === 'error') {
                    $errors[] = $result['message'];

                    continue;
                }

                if ($result['status'] === 'skipped') {
                    $skipped++;

                    continue;
                }

                // Project: find-or-create by name (case-insensitive)
                $projectKey = mb_strtolower(trim($row['project_name']));
                if (! isset($projectCache[$projectKey])) {
                    $project = Project::query()
                        ->whereRaw('LOWER(name) = ?', [$projectKey])
                        ->first();

                    if ($project === null) {
                        $mainContractor = $lookups['mainContractors']->first(
                            fn ($mc) => mb_strtolower($mc->name) === mb_strtolower(trim($row['main_contractor_name']))
                        );
                        $client = $lookups['clients']->first(
                            fn ($c) => mb_strtolower($c->name) === mb_strtolower(trim($row['client_name']))
                        );

                        $project = Project::query()->create([
                            'name' => trim($row['project_name']),
                            'main_contractor_id' => $mainContractor?->id,
                            'client_id' => $client?->id,
                            'start_date' => $this->parseDate($row['project_start_date'] ?? '') ?? now()->toDateString(),
                            'end_date' => $this->parseDate($row['project_end_date'] ?? ''),
                        ]);

                        $createdProjects++;
                    }

                    $projectCache[$projectKey] = $project;
                }

                $project = $projectCache[$projectKey];

                // Site: find-or-create by site_code within project
                $siteKey = $project->id.'_'.trim($row['site_code']);
                if (! isset($siteCache[$siteKey])) {
                    $siteTypeId = null;
                    if (($row['site_type'] ?? '') !== '') {
                        $siteTypeId = $lookups['siteTypes'][$row['site_type']] ?? null;
                    }

                    $machineTypeId = null;
                    if (($row['machine_type'] ?? '') !== '') {
                        $machineTypeId = $lookups['machineTypes'][$row['machine_type']] ?? null;
                    }

                    $existing = Site::query()->where('site_code', $row['site_code'])->first();

                    $coordinates = GoogleMapsCoordinates::fromUrl($row['google_map_url'] ?? '');

                    $siteAttributes = [
                        'project_id' => $project->id,
                        'location_name' => $row['location_name'],
                        'address' => $row['address'] ?: null,
                        'province' => $row['province'] ?: null,
                        'city' => $row['city'] ?: null,
                        'google_map_url' => $row['google_map_url'] ?: null,
                        'site_type_id' => $siteTypeId,
                        'machine_type_id' => $machineTypeId,
                        'power_kva' => $row['power_kva'] ?: null,
                        'bd_pic' => $row['bd_pic'] ?: null,
                        'ss_wo_number' => $row['ss_wo_number'] ?: null,
                        'cable_length_to_panel' => $row['cable_length_to_panel'] !== '' ? $row['cable_length_to_panel'] : null,
                        'charging_station_count' => $row['charging_station_count'] !== '' ? (int) $row['charging_station_count'] : null,
                    ];

                    if ($coordinates !== null) {
                        $siteAttributes['latitude'] = $coordinates['latitude'];
                        $siteAttributes['longitude'] = $coordinates['longitude'];
                    }

                    if ($existing !== null) {
                        $existing->fill($siteAttributes)->save();
                        $site = $existing;
                    } else {
                        $site = Site::query()->create([...$siteAttributes, 'site_code' => $row['site_code']]);
                        $createdSites++;
                    }

                    $siteCache[$siteKey] = $site;
                }

                $site = $siteCache[$siteKey];

                // Assignment: find-or-create by (site_id, activity_type)
                $activityType = ActivityType::from($row['activity_type']);
                $status = $row['status'] !== '' ? AssignmentStatus::from(strtoupper($row['status'])) : AssignmentStatus::Reported;
                $subcontractor = $lookups['subcontractors']->first(
                    fn ($s) => $s->code === trim($row['subcontractor_code'])
                );

                $assignment = Assignment::query()
                    ->where('site_id', $site->id)
                    ->where('activity_type', $activityType->value)
                    ->first();

                if ($assignment === null) {
                    $assignment = Assignment::query()->create([
                        'site_id' => $site->id,
                        'subcontractor_id' => $subcontractor?->id,
                        'activity_type' => $activityType,
                        'status' => $status,
                    ]);
                    $createdAssignments++;
                } else {
                    $assignment->status = $status;
                    if ($subcontractor !== null) {
                        $assignment->subcontractor_id = $subcontractor->id;
                    }
                    $assignment->save();
                }

                // Populate activity data from CSV columns.
                $this->upsertActivityData($assignment, $activityType, $row);
            }
        });

        return [
            'created_projects' => $createdProjects,
            'created_sites' => $createdSites,
            'created_assignments' => $createdAssignments,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, true>  $existingProjectKeys  lowercase names of projects already in DB
     * @return array{row_number: int|null, project_name: string, site_code: string, activity_type: string, status: string, message: string}
     */
    private function validateRow(array $row, int $rowNumber, array $lookups, Collection $existingProjectKeys): array
    {
        $base = [
            'row_number' => $rowNumber,
            'project_name' => $row['project_name'] ?? '',
            'site_code' => $row['site_code'] ?? '',
            'activity_type' => $row['activity_type'] ?? '',
        ];

        if (($row['project_name'] ?? '') === '') {
            return [
                ...$base,
                'status' => 'error',
                'message' => "Row {$rowNumber}: project_name is required.",
            ];
        }

        $projectKey = mb_strtolower(trim($row['project_name']));
        $isNewProject = ! $existingProjectKeys->has($projectKey);

        if ($isNewProject) {
            if (($row['main_contractor_name'] ?? '') === '') {
                return [
                    ...$base,
                    'status' => 'error',
                    'message' => "Row {$rowNumber}: main_contractor_name is required for new project '{$row['project_name']}'.",
                ];
            }

            if (($row['client_name'] ?? '') === '') {
                return [
                    ...$base,
                    'status' => 'error',
                    'message' => "Row {$rowNumber}: client_name is required for new project '{$row['project_name']}'.",
                ];
            }
        }

        if (($row['main_contractor_name'] ?? '') !== '') {
            $mcExists = $lookups['mainContractors']->contains(
                fn ($mc) => mb_strtolower($mc->name) === mb_strtolower(trim($row['main_contractor_name']))
            );
            if (! $mcExists) {
                return [
                    ...$base,
                    'status' => 'error',
                    'message' => "Row {$rowNumber}: main_contractor_name '{$row['main_contractor_name']}' not found.",
                ];
            }
        }

        if (($row['client_name'] ?? '') !== '') {
            $clientExists = $lookups['clients']->contains(
                fn ($c) => mb_strtolower($c->name) === mb_strtolower(trim($row['client_name']))
            );
            if (! $clientExists) {
                return [
                    ...$base,
                    'status' => 'error',
                    'message' => "Row {$rowNumber}: client_name '{$row['client_name']}' not found.",
                ];
            }
        }

        if (($row['site_code'] ?? '') === '') {
            return [
                ...$base,
                'status' => 'error',
                'message' => "Row {$rowNumber}: site_code is required.",
            ];
        }

        if (($row['location_name'] ?? '') === '') {
            return [
                ...$base,
                'status' => 'error',
                'message' => "Row {$rowNumber}: location_name is required.",
            ];
        }

        if (($row['activity_type'] ?? '') === '') {
            return [
                ...$base,
                'status' => 'skipped',
                'message' => "Row {$rowNumber}: activity_type is empty, row skipped.",
            ];
        }

        $activityType = ActivityType::tryFrom($row['activity_type']);
        if ($activityType === null) {
            $valid = implode(', ', array_column(ActivityType::cases(), 'value'));

            return [
                ...$base,
                'status' => 'error',
                'message' => "Row {$rowNumber}: invalid activity_type '{$row['activity_type']}'. Valid: {$valid}.",
            ];
        }

        if (($row['site_type'] ?? '') !== '' && ! isset($lookups['siteTypes'][$row['site_type']])) {
            return [
                ...$base,
                'status' => 'error',
                'message' => "Row {$rowNumber}: unknown site_type '{$row['site_type']}'. Available: ".$lookups['siteTypes']->keys()->join(', ').'.',
            ];
        }

        if (($row['machine_type'] ?? '') !== '' && ! isset($lookups['machineTypes'][$row['machine_type']])) {
            return [
                ...$base,
                'status' => 'error',
                'message' => "Row {$rowNumber}: unknown machine_type '{$row['machine_type']}'. Available: ".$lookups['machineTypes']->keys()->join(', ').'.',
            ];
        }

        if (($row['status'] ?? '') !== '') {
            $statusEnum = AssignmentStatus::tryFrom(strtoupper($row['status']));
            if ($statusEnum === null) {
                $valid = implode(', ', array_column(AssignmentStatus::cases(), 'value'));

                return [
                    ...$base,
                    'status' => 'error',
                    'message' => "Row {$rowNumber}: invalid status '{$row['status']}'. Valid: {$valid}.",
                ];
            }
        }

        if (($row['subcontractor_code'] ?? '') !== '') {
            $subExists = $lookups['subcontractors']->contains(
                fn ($s) => $s->code === trim($row['subcontractor_code'])
            );
            if (! $subExists) {
                return [
                    ...$base,
                    'status' => 'error',
                    'message' => "Row {$rowNumber}: subcontractor_code '{$row['subcontractor_code']}' not found.",
                ];
            }
        }

        // Validate all date-typed columns so bad formats show as preview errors.
        $dateColumns = [
            'project_start_date', 'project_end_date',
            'ss_schedule_date', 'ss_report_submission_date',
            'setup_approval_date', 'cons_actual_start_date', 'cons_actual_done_date',
            'go_live_date_pln', 'go_live_date_pln_pass',
            'kwh_meter_installation_date', 'nidi_slo_date_acquired', 'bpujl_acquired_date',
        ];

        foreach ($dateColumns as $col) {
            $val = $row[$col] ?? '';
            if ($val !== '' && $this->parseDate($val) === null) {
                return [
                    ...$base,
                    'status' => 'error',
                    'message' => "Row {$rowNumber}: invalid date '{$val}' for {$col}. Use YYYY-MM-DD or DD/MM/YYYY.",
                ];
            }
        }

        return [
            ...$base,
            'status' => 'ok',
            'message' => '',
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function upsertActivityData(Assignment $assignment, ActivityType $activityType, array $row): void
    {
        match ($activityType) {
            ActivityType::Survey => $this->upsertSurveyData($assignment, $row),
            ActivityType::Construction => $this->upsertConstructionData($assignment, $row),
            ActivityType::PlnConnection => $this->upsertPlnData($assignment, $row),
            ActivityType::Bast => $this->upsertBastData($assignment, $row),
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    private function upsertSurveyData(Assignment $assignment, array $row): void
    {
        AssignmentSurveyData::query()->updateOrCreate(
            ['assignment_id' => $assignment->id],
            array_filter([
                'ss_schedule_date' => $this->parseDate($row['ss_schedule_date'] ?? ''),
                'ss_report_submission_date' => $this->parseDate($row['ss_report_submission_date'] ?? ''),
                'cable_pulling_type' => $row['cable_pulling_type'] ?: null,
                'parking_slot' => $row['parking_slot'] ?: null,
            ], fn ($v) => $v !== null)
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function upsertConstructionData(Assignment $assignment, array $row): void
    {
        AssignmentConstructionData::query()->updateOrCreate(
            ['assignment_id' => $assignment->id],
            array_filter([
                'cons_wo_number' => $row['cons_wo_number'] ?: null,
                'setup_approval_date' => $this->parseDate($row['setup_approval_date'] ?? ''),
                'cons_actual_start_date' => $this->parseDate($row['cons_actual_start_date'] ?? ''),
                'cons_actual_done_date' => $this->parseDate($row['cons_actual_done_date'] ?? ''),
                'machine_serial_number' => $row['machine_serial_number'] ?: null,
                'go_live_date_pln' => $this->parseDate($row['go_live_date_pln'] ?? ''),
                'go_live_date_pln_pass' => $this->parseDate($row['go_live_date_pln_pass'] ?? ''),
            ], fn ($v) => $v !== null)
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function upsertPlnData(Assignment $assignment, array $row): void
    {
        AssignmentPlnData::query()->updateOrCreate(
            ['assignment_id' => $assignment->id],
            array_filter([
                'kwh_meter_installation_date' => $this->parseDate($row['kwh_meter_installation_date'] ?? ''),
                'type_rate' => $row['type_rate'] ?: null,
                'id_pelanggan' => $row['id_pelanggan'] ?: null,
                'nidi_slo_date_acquired' => $this->parseDate($row['nidi_slo_date_acquired'] ?? ''),
                'bpujl_acquired_date' => $this->parseDate($row['bpujl_acquired_date'] ?? ''),
            ], fn ($v) => $v !== null)
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function upsertBastData(Assignment $assignment, array $row): void
    {
        AssignmentBastData::query()->updateOrCreate(
            ['assignment_id' => $assignment->id],
            array_filter([
                'sim_provider' => $row['sim_provider'] ?: null,
                'nomor_simcard' => $row['nomor_simcard'] ?: null,
            ], fn ($v) => $v !== null)
        );
    }

    /**
     * Pre-load all lookup data to avoid N+1 queries.
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function loadLookups(): array
    {
        $errors = [];

        $mainContractors = MainContractor::query()->get(['id', 'name']);
        $clients = Client::query()->get(['id', 'name']);
        $subcontractors = Subcontractor::query()->get(['id', 'code', 'name']);
        $siteTypes = SiteType::query()->pluck('id', 'name');
        $machineTypes = MachineType::query()->pluck('id', 'name');

        return [
            [
                'mainContractors' => $mainContractors,
                'clients' => $clients,
                'subcontractors' => $subcontractors,
                'siteTypes' => $siteTypes,
                'machineTypes' => $machineTypes,
            ],
            $errors,
        ];
    }

    /**
     * Query which of the CSV's project names already exist in the database.
     *
     * @param  array<int, array<string, string>>  $rawRows
     * @return Collection<string, true> keyed by lowercase project name
     */
    private function preloadExistingProjectKeys(array $rawRows): Collection
    {
        $names = collect($rawRows)
            ->pluck('project_name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            return collect();
        }

        return Project::query()
            ->whereRaw('LOWER(name) IN ('.implode(',', array_fill(0, count($names), '?')).')', $names)
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->flip();
    }

    /**
     * Parse a date string into YYYY-MM-DD, accepting multiple input formats.
     *
     * Accepts: YYYY-MM-DD, DD/MM/YYYY, D/M/YYYY, DD-MM-YYYY, YYYY/MM/DD.
     * Returns null for empty input or unrecognisable format.
     */
    private function parseDate(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];

        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $v);
                // Strict check: createFromFormat is lenient; verify round-trip to reject garbage.
                if ($dt !== false && $dt->format($format) === $v) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Exception) {
                // Try next format.
            }
        }

        return null;
    }
}
