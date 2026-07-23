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

class SiteOperationsDashboardService
{
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
            'problem_breakdown' => $rows
                ->whereNotNull('issue_type')
                ->countBy('issue_type')
                ->sortDesc()
                ->all(),
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
                $issues = $this->siteIssues($site, $assignments, $mainContractorAdminOwners);
                $primaryIssue = $issues->first();
                $overallStatus = $this->overallStatus($assignments, $activeAssignments, $primaryIssue);
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
                    'main_issue' => $primaryIssue['problem'] ?? $this->defaultIssueText($overallStatus),
                    'issue_type' => $primaryIssue['type'] ?? null,
                    'issue_severity' => $primaryIssue['severity'] ?? null,
                    'issues' => $issues->values()->all(),
                    'severity_score' => $primaryIssue['severity_score'] ?? $this->statusSortScore($overallStatus),
                    'owner' => $primaryIssue['owner'] ?? null,
                    'age_days' => $primaryIssue['age_days'] ?? null,
                    'next_action' => $primaryIssue['recommended_action'] ?? $this->defaultActionText($overallStatus),
                    'url' => route('admin.assignments.site-assignments', $site->site_id),
                    'ai_prompt' => "Kenapa site {$site->site_code} belum selesai dan apa masalah utamanya?",
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
     * @param  Collection<int, object>  $assignments
     * @param  Collection<int, string>  $mainContractorAdminOwners
     * @return Collection<int, array<string, mixed>>
     */
    private function siteIssues(object $site, Collection $assignments, Collection $mainContractorAdminOwners): Collection
    {
        if ($assignments->whereNotNull('assignment_id')->isEmpty()) {
            return collect([
                $this->issue($site, null, 'medium', 45, 'no_assignment_started', 'No assignment has started for this location.', 'Main Contractor Admin', 'Create or assign the required workstreams for this site.'),
            ]);
        }

        $activeAssignments = $assignments->whereNotIn('status', [AssignmentStatus::Drop->value]);

        if ($activeAssignments->isEmpty()) {
            return collect([
                $this->issue($site, $assignments->first(), 'low', 10, 'site_dropped', 'All assignments for this site are dropped.', 'Main Contractor Admin', 'Confirm whether the site should remain dropped or be restored.'),
            ]);
        }

        return $activeAssignments
            ->flatMap(fn (object $row): array => $this->issuesForAssignment($site, $row, $mainContractorAdminOwners))
            ->sortByDesc('severity_score')
            ->values();
    }

    /**
     * @param  Collection<int, string>  $mainContractorAdminOwners
     * @return list<array<string, mixed>>
     */
    private function issuesForAssignment(object $site, object $row, Collection $mainContractorAdminOwners): array
    {
        $items = [];
        $ageDays = $row->updated_at ? (int) Carbon::parse($row->updated_at)->diffInDays(now()) : 0;
        $mainContractorOwner = $this->mainContractorOwner($site, $mainContractorAdminOwners);

        $items = [
            ...$items,
            ...$this->surveyIssues($site, $row, $mainContractorOwner),
            ...$this->plnIssues($site, $row),
            ...$this->constructionIssues($site, $row, $mainContractorOwner),
            ...$this->bastIssues($site, $row),
            ...$this->noteIssues($site, $row),
        ];

        if (
            $row->activity_type === ActivityType::Construction->value
            && blank($row->cons_wo_number)
            && ! in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value], true)
        ) {
            $items[] = $this->issue($site, $row, 'critical', 95, 'construction_missing_wo', 'Construction cannot proceed because WO number is missing.', $mainContractorOwner, 'Fill the construction WO number, then follow up the subcontractor.');
        }

        if ($row->status === AssignmentStatus::Revision->value) {
            $items[] = $this->issue($site, $row, 'high', 78, 'revision_pending', 'Assignment is in revision and needs correction.', $row->subcontractor_name ?? 'Subcontractor', 'Close the revision comments and resubmit for review.');
        }

        if (in_array($row->status, [AssignmentStatus::Pending->value, AssignmentStatus::Revision->value], true) && $ageDays >= 7) {
            $items[] = $this->issue($site, $row, $ageDays >= 14 ? 'critical' : 'high', $ageDays >= 14 ? 90 : 72, 'stalled_assignment', "{$row->status} has had no update for {$ageDays} days.", $row->status === AssignmentStatus::Revision->value ? ($row->subcontractor_name ?? 'Subcontractor') : $mainContractorOwner, 'Follow up the owner and force a progress update this week.');
        }

        if (in_array($row->status, array_map(fn (AssignmentStatus $status): string => $status->value, AssignmentStatus::verifiableStatuses()), true)) {
            $items[] = $this->issue($site, $row, 'medium', 58, 'ready_for_verification', "{$row->activity_type} is ready for admin verification.", $mainContractorOwner, 'Review data quality and verify when valid.');
        }

        if ($row->status === AssignmentStatus::Verified->value) {
            $items[] = $this->issue($site, $row, 'medium', 52, 'verified_not_reported', "{$row->activity_type} is verified but not reported.", $mainContractorOwner, 'Include this assignment in the next report generation.');
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function noteIssues(object $site, object $row): array
    {
        if ($this->isTerminal($row) || ! is_array($row->latest_comment ?? null)) {
            return [];
        }

        $body = (string) ($row->latest_comment['body'] ?? '');

        if (! $this->looksLikeBlockerNote($body)) {
            return [];
        }

        return [
            $this->issue($site, $row, 'medium', 64, 'note_blocker_signal', 'Latest note may explain the blocker: '.$body, $row->latest_comment['user']['name'] ?? ($row->subcontractor_name ?? 'Owner'), 'Review the latest note and update the structured blocker/status if needed.'),
        ];
    }

    private function looksLikeBlockerNote(string $body): bool
    {
        $normalized = mb_strtolower($body);

        foreach (['block', 'blocked', 'issue', 'problem', 'waiting', 'wait', 'hold', 'stuck', 'late', 'delay', 'akses', 'access', 'izin', 'tutup', 'pln', 'wo', 'revisi', 'kendala', 'belum', 'pending'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function surveyIssues(object $site, object $row, string $mainContractorOwner): array
    {
        if ($row->activity_type !== ActivityType::Survey->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $missingSchedule = $this->missingLabels($row, [
            'survey_schedule_date' => 'survey schedule',
        ]);
        $missingSiteData = $this->missingLabels((object) [
            'site_power_kva' => $site->power_kva,
            'survey_power_kva' => $row->survey_power_kva,
        ], [
            'site_power_kva' => 'site power KVA',
            'survey_power_kva' => 'survey power KVA',
        ]);
        $missingEvidence = $this->missingLabels($row, [
            'survey_photo_overall_site' => 'overall site photo',
            'survey_photo_parking_evcs' => 'EV parking photo',
            'survey_photo_access_route' => 'access route photo',
            'survey_photo_pln_network' => 'PLN network photo',
            'survey_photo_satellite_gmaps' => 'satellite map photo',
            'survey_file_site_plan' => 'site plan',
            'survey_file_ba_survey' => 'BA survey',
        ]);

        if ($missingSchedule !== []) {
            $issues[] = $this->issue($site, $row, 'high', 84, 'survey_schedule_missing', 'Survey schedule is missing.', $row->subcontractor_name ?? 'Subcontractor', 'Ask the subcontractor to set the survey date.');
        }

        if ($missingSiteData !== []) {
            $issues[] = $this->issue($site, $row, 'high', 82, 'site_missing_power', 'Survey/location power data is missing: '.$this->labelList($missingSiteData).'.', $mainContractorOwner, 'Complete site and survey power KVA before document/report work.');
        }

        if ($missingEvidence !== []) {
            $issues[] = $this->issue($site, $row, 'high', 76, 'survey_evidence_missing', 'Survey evidence is incomplete: '.$this->labelList($missingEvidence).'.', $row->subcontractor_name ?? 'Subcontractor', 'Upload the missing survey photos and documents.');
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plnIssues(object $site, object $row): array
    {
        if ($row->activity_type !== ActivityType::PlnConnection->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $owner = $row->subcontractor_name ?? 'Subcontractor';

        $missingRegistration = $this->missingLabels($row, [
            'pln_file_reg' => 'registration file',
            'pln_email_bpujl_req_date' => 'BPUJL request date',
        ]);

        if (in_array($row->status, [AssignmentStatus::Pending->value, AssignmentStatus::Registration->value], true) && $missingRegistration !== []) {
            $issues[] = $this->issue($site, $row, 'high', 83, 'pln_registration_incomplete', 'PLN registration is incomplete: '.$this->labelList($missingRegistration).'.', $owner, 'Complete PLN registration evidence and BPUJL request.');
        }

        $missingBilling = $this->missingLabels($row, [
            'pln_bpujl_acquired_date' => 'BPUJL acquired date',
            'pln_file_pk' => 'PK file',
        ]);

        if (in_array($row->status, [AssignmentStatus::Billing->value, AssignmentStatus::Connection->value, AssignmentStatus::KwhDone->value], true) && $missingBilling !== []) {
            $issues[] = $this->issue($site, $row, 'high', 79, 'pln_billing_incomplete', 'PLN billing/PK evidence is incomplete: '.$this->labelList($missingBilling).'.', $owner, 'Complete BPUJL and PK evidence before connection closeout.');
        }

        $missingKwh = $this->missingLabels($row, [
            'pln_kwh_meter_installation_date' => 'kWh installation date',
            'pln_id_pelanggan' => 'customer ID',
            'pln_foto_kwh' => 'kWh photo',
        ]);

        if (in_array($row->status, [AssignmentStatus::Connection->value, AssignmentStatus::KwhDone->value], true) && $missingKwh !== []) {
            $issues[] = $this->issue($site, $row, 'high', 81, 'pln_kwh_incomplete', 'PLN kWh closeout is incomplete: '.$this->labelList($missingKwh).'.', $owner, 'Complete kWh installation data, customer ID, and kWh photo.');
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function constructionIssues(object $site, object $row, string $mainContractorOwner): array
    {
        if ($row->activity_type !== ActivityType::Construction->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $owner = $row->subcontractor_name ?? 'Subcontractor';

        if (filled($row->cons_wo_number)) {
            $missingExecution = $this->missingLabels($row, [
                'cons_actual_start_date' => 'actual start date',
                'cons_actual_done_date' => 'actual done date',
                'machine_serial_number' => 'machine serial number',
                'foto_machine_sn' => 'machine serial photo',
            ]);

            if (in_array($row->status, [AssignmentStatus::Construction->value, AssignmentStatus::MachineOnsite->value, AssignmentStatus::Done->value, AssignmentStatus::Live->value], true) && $missingExecution !== []) {
                $issues[] = $this->issue($site, $row, 'high', 80, 'construction_data_incomplete', 'Construction data is incomplete: '.$this->labelList($missingExecution).'.', $owner, 'Complete construction dates, machine serial data, and serial photo.');
            }

            if ((int) ($row->construction_photo_count ?? 0) === 0 && in_array($row->status, [AssignmentStatus::Done->value, AssignmentStatus::Live->value], true)) {
                $issues[] = $this->issue($site, $row, 'high', 77, 'construction_photos_missing', 'Construction is marked advanced but has no construction photos.', $owner, 'Upload construction progress/completion photos.');
            }

            if ($row->status === AssignmentStatus::Done->value && blank($row->go_live_date_pln) && blank($row->go_live_date_pln_pass)) {
                $issues[] = $this->issue($site, $row, 'medium', 62, 'construction_not_live', 'Construction is done but go-live dates are missing.', $mainContractorOwner, 'Confirm go-live readiness and fill the PLN go-live date.');
            }
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bastIssues(object $site, object $row): array
    {
        if ($row->activity_type !== ActivityType::Bast->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $missingSim = $this->missingLabels($row, [
            'bast_sim_provider' => 'SIM provider',
            'bast_nomor_simcard' => 'SIM number',
        ]);

        if ((int) ($row->bast_sim_photo_count ?? 0) < 2) {
            $missingSim[] = 'SIM card photos';
        }

        if ($missingSim !== []) {
            $issues[] = $this->issue($site, $row, 'high', 78, 'bast_sim_missing', 'SIM card data/evidence is incomplete: '.$this->labelList($missingSim).'.', $row->subcontractor_name ?? 'Subcontractor', 'Complete SIM provider, SIM number, and SIM card checkpoint photos.');
        }

        $missingCore = $this->missingLabels($row, [
            'bast_plant_name' => 'plant name',
            'bast_installation_date' => 'installation date',
            'bast_commissioning_date' => 'commissioning date',
        ]);

        if ($missingCore === [] && (int) ($row->bast_photo_count ?? 0) > 0) {
            return $issues;
        }

        $missing = $missingCore;

        if ((int) ($row->bast_photo_count ?? 0) === 0) {
            $missing[] = 'BAST photos';
        }

        $issues[] = $this->issue($site, $row, 'high', 75, 'bast_evidence_missing', 'BAST evidence is incomplete: '.$this->labelList($missing).'.', $row->subcontractor_name ?? 'Subcontractor', 'Complete BAST fields and upload required checkpoint photos.');

        return $issues;
    }

    private function isTerminal(object $row): bool
    {
        return in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value, AssignmentStatus::Drop->value], true);
    }

    /**
     * @param  array<string, string>  $labelsByField
     * @return list<string>
     */
    private function missingLabels(object $row, array $labelsByField): array
    {
        $missing = [];

        foreach ($labelsByField as $field => $label) {
            if (blank($row->{$field} ?? null)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $labels
     */
    private function labelList(array $labels): string
    {
        return collect($labels)->take(4)->join(', ');
    }

    private function issue(object $site, ?object $row, string $severity, int $score, string $type, string $problem, string $owner, string $recommendedAction): array
    {
        return [
            'severity' => $severity,
            'severity_score' => $score,
            'type' => $type,
            'problem' => $problem,
            'owner' => $owner,
            'recommended_action' => $recommendedAction,
            'assignment_id' => $row !== null && isset($row->assignment_id) ? (int) $row->assignment_id : null,
            'activity_type' => $row?->activity_type,
            'status' => $row?->status,
            'age_days' => $row?->updated_at ? (int) Carbon::parse($row->updated_at)->diffInDays(now()) : null,
            'site_id' => (int) $site->site_id,
        ];
    }

    private function overallStatus(Collection $assignments, Collection $activeAssignments, ?array $primaryIssue): string
    {
        if ($assignments->whereNotNull('assignment_id')->isEmpty()) {
            return 'not_started';
        }

        if ($activeAssignments->isEmpty()) {
            return 'dropped';
        }

        if ($activeAssignments->every(fn (object $row): bool => in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value], true))) {
            return 'done';
        }

        return match ($primaryIssue['type'] ?? null) {
            'construction_missing_wo',
            'site_missing_power',
            'survey_schedule_missing',
            'survey_evidence_missing',
            'pln_registration_incomplete',
            'pln_billing_incomplete',
            'pln_kwh_incomplete',
            'construction_data_incomplete',
            'construction_photos_missing',
            'bast_sim_missing',
            'bast_evidence_missing' => 'blocked',
            'stalled_assignment' => 'stalled',
            'revision_pending' => 'stalled',
            'ready_for_verification' => 'needs_review',
            'verified_not_reported' => 'ready_for_report',
            default => 'in_progress',
        };
    }

    private function defaultIssueText(string $overallStatus): string
    {
        return match ($overallStatus) {
            'done' => 'All active assignments for this location are verified or reported.',
            'dropped' => 'All assignments for this location are dropped.',
            'in_progress' => 'Work is in progress with no critical blocker detected.',
            default => 'No active issue detected.',
        };
    }

    private function defaultActionText(string $overallStatus): string
    {
        return match ($overallStatus) {
            'done' => 'No action needed.',
            'dropped' => 'Confirm site scope if it should be restored.',
            'in_progress' => 'Monitor workstream updates.',
            default => 'Review this location.',
        };
    }

    private function statusSortScore(string $overallStatus): int
    {
        return match ($overallStatus) {
            'blocked' => 95,
            'stalled' => 80,
            'needs_review' => 58,
            'ready_for_report' => 52,
            'not_started' => 45,
            'in_progress' => 30,
            'dropped' => 10,
            'done' => 0,
            default => 0,
        };
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
