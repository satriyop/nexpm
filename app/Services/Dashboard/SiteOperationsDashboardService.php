<?php

namespace App\Services\Dashboard;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteOperationsDashboardService
{
    public function __construct(private SiteFlowEvaluator $siteFlowEvaluator) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $rows = $this->prioritizedRows($user, $mainContractorFilter, $projectFilter);
        $filteredRows = $this->applyFilters($rows, $filters)->values();
        $pageRows = $filteredRows
            ->forPage($filters['page'], $filters['per_page'])
            ->values();
        $totalFilteredRows = $filteredRows->count();
        $lastPage = max(1, (int) ceil($totalFilteredRows / $filters['per_page']));

        return [
            'generated_at' => now()->toIso8601String(),
            'metrics' => [
                'total_sites' => $rows->count(),
                'done_sites' => $rows->where('overall_status', 'done')->count(),
                'blocked_sites' => $rows->where('overall_status', 'blocked')->count(),
                'stalled_sites' => $rows->where('overall_status', 'stalled')->count(),
                'needs_review_sites' => $rows->where('overall_status', 'needs_review')->count(),
                'ready_for_report_sites' => $rows->where('overall_status', 'ready_for_report')->count(),
                'not_started_sites' => $rows->where('overall_status', 'not_started')->count(),
                'matching_sites' => $totalFilteredRows,
            ],
            'problem_breakdown' => $this->breakdownByField($rows, 'root_blocker_type'),
            'root_blocker_breakdown' => $this->breakdownByField($rows, 'root_blocker_type'),
            'symptom_breakdown' => $this->breakdownByField($rows, 'primary_symptom_type'),
            'filter_options' => [
                'statuses' => $rows->pluck('overall_status')->filter()->unique()->sort()->values()->all(),
                'issue_types' => $this->issueTypeOptions($rows),
                'machine_types' => $rows->pluck('machine_type')->filter()->unique()->sort()->values()->all(),
                'owners' => $rows->pluck('owner')->filter()->unique()->sort()->values()->all(),
                'wo_numbers' => $rows->pluck('construction_wo_number')->filter()->unique()->sort()->values()->all(),
            ],
            'active_filters' => $filters,
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $totalFilteredRows,
                'last_page' => $lastPage,
                'from' => $totalFilteredRows === 0 ? 0 : (($filters['page'] - 1) * $filters['per_page']) + 1,
                'to' => min($filters['page'] * $filters['per_page'], $totalFilteredRows),
            ],
            'site_rows' => $pageRows->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function exportRows(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null, array $filters = []): Collection
    {
        $filters = $this->normalizeFilters($filters);

        return $this->applyFilters(
            $this->prioritizedRows($user, $mainContractorFilter, $projectFilter),
            $filters
        )->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function prioritizedRows(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): Collection
    {
        return $this->siteRows($user, $mainContractorFilter, $projectFilter)
            ->sortByDesc(fn (array $row): int => (int) $row['severity_score'])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{status: ?string, issue_type: ?string, machine_type: ?string, owner: ?string, wo_number: ?string, search: ?string, page: int, per_page: int}
     */
    private function normalizeFilters(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 50;

        return [
            'status' => $this->filterValue($filters['status'] ?? null),
            'issue_type' => $this->filterValue($filters['issue_type'] ?? null),
            'machine_type' => $this->filterValue($filters['machine_type'] ?? null),
            'owner' => $this->filterValue($filters['owner'] ?? null),
            'wo_number' => $this->filterValue($filters['wo_number'] ?? null),
            'search' => $this->filterValue($filters['search'] ?? null),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => $perPage,
        ];
    }

    private function filterValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' || $value === '__all__' ? null : $value;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{status: ?string, issue_type: ?string, machine_type: ?string, owner: ?string, wo_number: ?string, search: ?string, page: int, per_page: int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        return $rows
            ->when($filters['status'], fn (Collection $items, string $status): Collection => $items->where('overall_status', $status))
            ->when($filters['issue_type'], fn (Collection $items, string $issueType): Collection => $items->filter(
                fn (array $row): bool => collect($row['issues'] ?? [])->pluck('type')->contains($issueType)
            ))
            ->when($filters['machine_type'], fn (Collection $items, string $machineType): Collection => $items->where('machine_type', $machineType))
            ->when($filters['owner'], fn (Collection $items, string $owner): Collection => $items->where('owner', $owner))
            ->when($filters['wo_number'], fn (Collection $items, string $woNumber): Collection => $items->where('construction_wo_number', $woNumber))
            ->when($filters['search'], function (Collection $items, string $search): Collection {
                $needle = mb_strtolower($search);

                return $items->filter(function (array $row) use ($needle): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $row['site_code'] ?? null,
                        $row['location_name'] ?? null,
                        $row['project'] ?? null,
                        $row['main_contractor'] ?? null,
                        $row['machine_type'] ?? null,
                        $row['construction_wo_number'] ?? null,
                        $row['main_issue'] ?? null,
                        $row['owner'] ?? null,
                        $row['latest_note']['body'] ?? null,
                        $row['flow_explanation'] ?? null,
                    ])));

                    return str_contains($haystack, $needle);
                });
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<string>
     */
    private function issueTypeOptions(Collection $rows): array
    {
        return $rows
            ->flatMap(fn (array $row): array => collect($row['issues'] ?? [])->pluck('type')->all())
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function breakdownByField(Collection $rows, string $field): array
    {
        return $rows
            ->pluck($field)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function siteRows(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): Collection
    {
        $assignmentRows = $this->assignmentRows($user, $mainContractorFilter, $projectFilter);
        $latestCommentsByAssignment = $this->latestCommentsByAssignment($assignmentRows);
        $assignmentRows = $assignmentRows->map(function (object $row) use ($latestCommentsByAssignment): object {
            $row->latest_comment = $row->assignment_id !== null
                ? $latestCommentsByAssignment->get((int) $row->assignment_id)
                : null;

            return $row;
        });
        $assignmentsBySite = $assignmentRows->whereNotNull('assignment_id')->groupBy('site_id');
        $mainContractorAdminOwners = $this->mainContractorAdminOwners($assignmentRows);

        return $this->sites($user, $mainContractorFilter, $projectFilter)
            ->map(function (object $site) use ($assignmentsBySite, $mainContractorAdminOwners): array {
                $assignments = $assignmentsBySite->get($site->site_id, collect());
                $workstreams = $this->workstreams($assignments);
                $latestNote = $this->latestNote($assignments);
                $activeAssignments = $assignments->whereNotIn('status', [AssignmentStatus::Drop->value]);
                $completedAssignments = $activeAssignments->whereIn('status', [
                    AssignmentStatus::Verified->value,
                    AssignmentStatus::Reported->value,
                ]);
                $flow = $this->siteFlowEvaluator->evaluate($site, $assignments, $mainContractorAdminOwners);
                $issues = $flow['issues'];
                $primaryIssue = $flow['primary_issue'];
                $rootIssue = $flow['root_issue'];
                $primarySymptom = $flow['primary_symptom'];
                $overallStatus = $flow['overall_status'];
                $activeCount = $activeAssignments->count();
                $constructionWoNumber = $this->constructionWoNumber($assignments);

                return [
                    'site_id' => (int) $site->site_id,
                    'site_code' => $site->site_code,
                    'location_name' => $site->location_name,
                    'project_id' => (int) $site->project_id,
                    'project' => $site->project_name,
                    'main_contractor' => $site->main_contractor_name,
                    'machine_type' => $site->machine_type_name,
                    'construction_wo_number' => $constructionWoNumber,
                    'overall_status' => $overallStatus,
                    'completion_pct' => $activeCount > 0 ? (int) round($completedAssignments->count() / $activeCount * 100) : 0,
                    'active_assignment_count' => $activeCount,
                    'workstreams' => $workstreams,
                    'latest_note' => $latestNote,
                    'current_stage' => $flow['current_stage'],
                    'flow_explanation' => $flow['flow_explanation'],
                    'main_issue' => $primaryIssue['problem'] ?? $this->siteFlowEvaluator->defaultIssueText($overallStatus),
                    'issue_type' => $primaryIssue['type'] ?? null,
                    'issue_severity' => $primaryIssue['severity'] ?? null,
                    'root_blocker_type' => $rootIssue['type'] ?? null,
                    'root_blocker' => $rootIssue,
                    'primary_symptom_type' => $primarySymptom['type'] ?? null,
                    'primary_symptom' => $primarySymptom,
                    'issues' => $issues->values()->all(),
                    'severity_score' => $primaryIssue['severity_score'] ?? $this->siteFlowEvaluator->statusSortScore($overallStatus),
                    'owner' => $primaryIssue['owner'] ?? null,
                    'age_days' => $primaryIssue['age_days'] ?? null,
                    'next_action' => $primaryIssue['recommended_action'] ?? $this->siteFlowEvaluator->defaultActionText($overallStatus),
                    'url' => route('admin.assignments.site-assignments', $site->site_id),
                    'ai_prompt' => $this->siteAiPrompt(
                        site: $site,
                        overallStatus: $overallStatus,
                        currentStage: $flow['current_stage'],
                        rootIssue: $rootIssue,
                        primarySymptom: $primarySymptom,
                        flowExplanation: $flow['flow_explanation'],
                        owner: $primaryIssue['owner'] ?? null,
                        nextAction: $primaryIssue['recommended_action'] ?? $this->siteFlowEvaluator->defaultActionText($overallStatus),
                        latestNote: $latestNote,
                    ),
                ];
            });
    }

    /**
     * @return Collection<int, object>
     */
    private function sites(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): Collection
    {
        return DB::table('sites')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->join('main_contractors', 'main_contractors.id', '=', 'projects.main_contractor_id')
            ->leftJoin('machine_types', 'machine_types.id', '=', 'sites.machine_type_id')
            ->tap(fn (Builder $query) => $this->applyTenantScope($query, $user, $mainContractorFilter))
            ->when($projectFilter, fn (Builder $query) => $query->where('projects.id', $projectFilter))
            ->select([
                'sites.id as site_id',
                'sites.site_code',
                'sites.location_name',
                'sites.power_kva',
                'sites.project_id',
                'projects.name as project_name',
                'projects.main_contractor_id',
                'main_contractors.name as main_contractor_name',
                'machine_types.name as machine_type_name',
            ])
            ->orderBy('projects.name')
            ->orderBy('sites.site_code')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function assignmentRows(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): Collection
    {
        $constructionPhotoCounts = DB::table('assignment_construction_photos')
            ->select([
                'assignment_construction_data_id',
                DB::raw('count(*) as construction_photo_count'),
            ])
            ->groupBy('assignment_construction_data_id');
        $bastPhotoCounts = DB::table('assignment_bast_photos')
            ->select([
                'assignment_bast_data_id',
                DB::raw('count(*) as bast_photo_count'),
                DB::raw("sum(case when checkpoint_key in ('sim_kartu_perdana', 'sim_installed_sim_card') then 1 else 0 end) as bast_sim_photo_count"),
            ])
            ->groupBy('assignment_bast_data_id');

        return DB::table('sites')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->join('main_contractors', 'main_contractors.id', '=', 'projects.main_contractor_id')
            ->leftJoin('assignments', 'assignments.site_id', '=', 'sites.id')
            ->leftJoin('subcontractors', 'subcontractors.id', '=', 'assignments.subcontractor_id')
            ->leftJoin('assignment_survey_data', 'assignment_survey_data.assignment_id', '=', 'assignments.id')
            ->leftJoin('assignment_pln_data', 'assignment_pln_data.assignment_id', '=', 'assignments.id')
            ->leftJoin('assignment_construction_data', 'assignment_construction_data.assignment_id', '=', 'assignments.id')
            ->leftJoin('assignment_bast_data', 'assignment_bast_data.assignment_id', '=', 'assignments.id')
            ->leftJoinSub($constructionPhotoCounts, 'construction_photo_counts', fn ($join) => $join->on('construction_photo_counts.assignment_construction_data_id', '=', 'assignment_construction_data.id'))
            ->leftJoinSub($bastPhotoCounts, 'bast_photo_counts', fn ($join) => $join->on('bast_photo_counts.assignment_bast_data_id', '=', 'assignment_bast_data.id'))
            ->tap(fn (Builder $query) => $this->applyTenantScope($query, $user, $mainContractorFilter))
            ->when($projectFilter, fn (Builder $query) => $query->where('projects.id', $projectFilter))
            ->select([
                'sites.id as site_id',
                'sites.site_code',
                'sites.location_name',
                'sites.power_kva',
                'projects.main_contractor_id',
                'main_contractors.name as main_contractor_name',
                'assignments.id as assignment_id',
                'assignments.activity_type',
                'assignments.status',
                'assignments.updated_at',
                'assignments.verified_at',
                'assignments.reported_at',
                'assignments.revision_comment',
                'assignments.unverify_reason',
                'subcontractors.name as subcontractor_name',
                'assignment_survey_data.ss_schedule_date as survey_schedule_date',
                'assignment_survey_data.power_kva as survey_power_kva',
                'assignment_survey_data.photo_overall_site as survey_photo_overall_site',
                'assignment_survey_data.photo_parking_evcs as survey_photo_parking_evcs',
                'assignment_survey_data.photo_access_route as survey_photo_access_route',
                'assignment_survey_data.photo_pln_network as survey_photo_pln_network',
                'assignment_survey_data.photo_satellite_gmaps as survey_photo_satellite_gmaps',
                'assignment_survey_data.file_site_plan as survey_file_site_plan',
                'assignment_survey_data.file_ba_survey as survey_file_ba_survey',
                'assignment_pln_data.file_reg as pln_file_reg',
                'assignment_pln_data.file_pk as pln_file_pk',
                'assignment_pln_data.file_slo as pln_file_slo',
                'assignment_pln_data.file_nidi as pln_file_nidi',
                'assignment_pln_data.email_bpujl_req_date as pln_email_bpujl_req_date',
                'assignment_pln_data.bpujl_acquired_date as pln_bpujl_acquired_date',
                'assignment_pln_data.kwh_meter_installation_date as pln_kwh_meter_installation_date',
                'assignment_pln_data.id_pelanggan as pln_id_pelanggan',
                'assignment_pln_data.foto_kwh as pln_foto_kwh',
                'assignment_construction_data.cons_wo_number',
                'assignment_construction_data.cons_actual_start_date',
                'assignment_construction_data.cons_actual_done_date',
                'assignment_construction_data.machine_serial_number',
                'assignment_construction_data.foto_machine_sn',
                'assignment_construction_data.go_live_date_pln',
                'assignment_construction_data.go_live_date_pln_pass',
                'assignment_bast_data.plant_name as bast_plant_name',
                'assignment_bast_data.sim_provider as bast_sim_provider',
                'assignment_bast_data.nomor_simcard as bast_nomor_simcard',
                'assignment_bast_data.installation_date as bast_installation_date',
                'assignment_bast_data.commissioning_date as bast_commissioning_date',
                DB::raw('coalesce(construction_photo_counts.construction_photo_count, 0) as construction_photo_count'),
                DB::raw('coalesce(bast_photo_counts.bast_photo_count, 0) as bast_photo_count'),
                DB::raw('coalesce(bast_photo_counts.bast_sim_photo_count, 0) as bast_sim_photo_count'),
            ])
            ->get();
    }

    /**
     * @param  Collection<int, object>  $assignmentRows
     * @return Collection<int, array<string, mixed>>
     */
    private function latestCommentsByAssignment(Collection $assignmentRows): Collection
    {
        $assignmentIds = $assignmentRows
            ->pluck('assignment_id')
            ->filter()
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return collect();
        }

        $latestCommentIds = DB::table('assignment_comments')
            ->whereIn('assignment_id', $assignmentIds)
            ->select([
                'assignment_id',
                DB::raw('max(id) as latest_comment_id'),
            ])
            ->groupBy('assignment_id');

        return DB::table('assignment_comments')
            ->joinSub($latestCommentIds, 'latest_comments', fn ($join) => $join->on('latest_comments.latest_comment_id', '=', 'assignment_comments.id'))
            ->leftJoin('users', 'users.id', '=', 'assignment_comments.user_id')
            ->select([
                'assignment_comments.id',
                'assignment_comments.assignment_id',
                'assignment_comments.body',
                'assignment_comments.created_at',
                'users.name as user_name',
                'users.role as user_role',
            ])
            ->get()
            ->mapWithKeys(fn (object $comment): array => [
                (int) $comment->assignment_id => $this->commentPayload($comment),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function commentPayload(object $comment): array
    {
        return [
            'id' => (int) $comment->id,
            'assignment_id' => (int) $comment->assignment_id,
            'body' => $comment->body,
            'created_at' => $comment->created_at,
            'user' => [
                'name' => $comment->user_name,
                'role' => $comment->user_role,
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $assignments
     * @return array<string, array<string, mixed>|null>
     */
    private function workstreams(Collection $assignments): array
    {
        $byActivity = $assignments
            ->whereNotNull('assignment_id')
            ->groupBy('activity_type')
            ->map(fn (Collection $rows): object => $rows->sortByDesc('updated_at')->first());

        return collect(ActivityType::cases())
            ->mapWithKeys(fn (ActivityType $activity): array => [
                $this->workstreamKey($activity) => $this->workstreamRow($activity, $byActivity->get($activity->value)),
            ])
            ->all();
    }

    private function workstreamKey(ActivityType $activity): string
    {
        return match ($activity) {
            ActivityType::Survey => 'survey',
            ActivityType::PlnConnection => 'pln',
            ActivityType::Construction => 'construction',
            ActivityType::Bast => 'bast',
        };
    }

    private function workstreamRow(ActivityType $activity, ?object $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'assignment_id' => (int) $row->assignment_id,
            'activity_type' => $activity->value,
            'label' => $activity->label(),
            'status' => $row->status,
            'subcontractor' => $row->subcontractor_name,
            'wo_number' => $activity === ActivityType::Construction ? $row->cons_wo_number : null,
            'age_days' => $row->updated_at ? (int) Carbon::parse($row->updated_at)->diffInDays(now()) : null,
            'revision_comment' => $row->revision_comment,
            'unverify_reason' => $row->unverify_reason,
            'latest_comment' => $row->latest_comment,
            'url' => route('admin.assignments.show', $row->assignment_id),
        ];
    }

    /**
     * @param  Collection<int, object>  $assignments
     * @return array<string, mixed>|null
     */
    private function latestNote(Collection $assignments): ?array
    {
        /** @var object|null $row */
        $row = $assignments
            ->whereNotNull('latest_comment')
            ->sortByDesc(fn (object $assignment): string => (string) ($assignment->latest_comment['created_at'] ?? ''))
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            ...$row->latest_comment,
            'activity_type' => $row->activity_type,
            'status' => $row->status,
            'url' => route('admin.assignments.show', $row->assignment_id),
        ];
    }

    /**
     * @param  Collection<int, object>  $assignments
     */
    private function constructionWoNumber(Collection $assignments): ?string
    {
        return $assignments
            ->where('activity_type', ActivityType::Construction->value)
            ->pluck('cons_wo_number')
            ->filter()
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $currentStage
     * @param  array<string, mixed>|null  $rootIssue
     * @param  array<string, mixed>|null  $primarySymptom
     * @param  array<string, mixed>|null  $latestNote
     */
    private function siteAiPrompt(object $site, string $overallStatus, ?array $currentStage, ?array $rootIssue, ?array $primarySymptom, string $flowExplanation, ?string $owner, string $nextAction, ?array $latestNote): string
    {
        $lines = [
            "Jelaskan kenapa site ini belum selesai dan apa masalah utamanya: {$site->site_code} / {$site->location_name}.",
            "Gunakan tool contextual_page_summary untuk site_id {$site->site_id}, lalu jawab berdasarkan konteks dashboard site evaluator berikut.",
            "Status site: {$overallStatus}",
            'Current stage: '.($currentStage['label'] ?? '-'),
            'Root blocker: '.($rootIssue['type'] ?? '-').' - '.($rootIssue['problem'] ?? '-'),
            'Operational symptom: '.($primarySymptom['type'] ?? '-').' - '.($primarySymptom['problem'] ?? '-'),
            "Flow explanation: {$flowExplanation}",
            'Owner: '.($owner ?? '-'),
            "Next action: {$nextAction}",
            'Latest note: '.Str::limit((string) ($latestNote['body'] ?? '-'), 240),
            'Format jawaban ringkas: akar masalah, bukti, dampak ke workflow, dan tindakan berikutnya.',
        ];

        return Str::limit(implode("\n", $lines), 1900, '');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, string>
     */
    private function mainContractorAdminOwners(Collection $rows): Collection
    {
        $mainContractorIds = $rows
            ->pluck('main_contractor_id')
            ->filter()
            ->unique()
            ->values();

        if ($mainContractorIds->isEmpty()) {
            return collect();
        }

        return DB::table('users')
            ->where('role', Role::Admin->value)
            ->whereIn('main_contractor_id', $mainContractorIds)
            ->orderBy('name')
            ->select(['name', 'main_contractor_id'])
            ->get()
            ->groupBy('main_contractor_id')
            ->map(function (Collection $admins): string {
                $adminNames = $admins->pluck('name');
                $extraAdminCount = max(0, $adminNames->count() - 2);
                $label = 'Admin: '.$adminNames->take(2)->join(', ');

                return $extraAdminCount > 0 ? "{$label} +{$extraAdminCount}" : $label;
            });
    }

    /**
     * @param  Collection<int, string>  $mainContractorAdminOwners
     */
    private function mainContractorOwner(object $site, Collection $mainContractorAdminOwners): string
    {
        $adminOwner = $mainContractorAdminOwners->get((int) $site->main_contractor_id);

        if (filled($adminOwner)) {
            return $adminOwner;
        }

        if (filled($site->main_contractor_name)) {
            return "{$site->main_contractor_name} Admin";
        }

        return 'Main Contractor Admin';
    }

    private function applyTenantScope(Builder $query, User $user, ?int $mainContractorFilter = null): void
    {
        if (! $user->isSuperAdmin()) {
            $query->where('projects.main_contractor_id', $user->main_contractor_id);

            return;
        }

        if ($mainContractorFilter) {
            $query->where('projects.main_contractor_id', $mainContractorFilter);
        }
    }
}
