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
    public function build(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): array
    {
        $rows = $this->siteRows($user, $mainContractorFilter, $projectFilter);
        $priorityRows = $rows
            ->sortByDesc(fn (array $row): int => (int) $row['severity_score'])
            ->take(50)
            ->values();

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
            ],
            'problem_breakdown' => $rows
                ->whereNotNull('issue_type')
                ->countBy('issue_type')
                ->sortDesc()
                ->all(),
            'filter_options' => [
                'statuses' => $priorityRows->pluck('overall_status')->filter()->unique()->sort()->values()->all(),
                'issue_types' => $priorityRows->pluck('issue_type')->filter()->unique()->sort()->values()->all(),
                'machine_types' => $priorityRows->pluck('machine_type')->filter()->unique()->sort()->values()->all(),
                'owners' => $priorityRows->pluck('owner')->filter()->unique()->sort()->values()->all(),
                'wo_numbers' => $priorityRows->pluck('construction_wo_number')->filter()->unique()->sort()->values()->all(),
            ],
            'site_rows' => $priorityRows->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function siteRows(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): Collection
    {
        $assignmentRows = $this->assignmentRows($user, $mainContractorFilter, $projectFilter);
        $assignmentsBySite = $assignmentRows->whereNotNull('assignment_id')->groupBy('site_id');
        $mainContractorAdminOwners = $this->mainContractorAdminOwners($assignmentRows);

        return $this->sites($user, $mainContractorFilter, $projectFilter)
            ->map(function (object $site) use ($assignmentsBySite, $mainContractorAdminOwners): array {
                $assignments = $assignmentsBySite->get($site->site_id, collect());
                $workstreams = $this->workstreams($assignments);
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
                    'main_issue' => $primaryIssue['problem'] ?? $this->defaultIssueText($overallStatus),
                    'issue_type' => $primaryIssue['type'] ?? null,
                    'issue_severity' => $primaryIssue['severity'] ?? null,
                    'issues' => $issues->take(5)->values()->all(),
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
        return DB::table('sites')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->join('main_contractors', 'main_contractors.id', '=', 'projects.main_contractor_id')
            ->leftJoin('assignments', 'assignments.site_id', '=', 'sites.id')
            ->leftJoin('subcontractors', 'subcontractors.id', '=', 'assignments.subcontractor_id')
            ->leftJoin('assignment_construction_data', 'assignment_construction_data.assignment_id', '=', 'assignments.id')
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
                'subcontractors.name as subcontractor_name',
                'assignment_construction_data.cons_wo_number',
            ])
            ->get();
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

        if (
            $row->activity_type === ActivityType::Construction->value
            && blank($row->cons_wo_number)
            && ! in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value], true)
        ) {
            $items[] = $this->issue($site, $row, 'critical', 95, 'construction_missing_wo', 'Construction cannot proceed because WO number is missing.', $mainContractorOwner, 'Fill the construction WO number, then follow up the subcontractor.');
        }

        if ($row->activity_type === ActivityType::Survey->value && blank($site->power_kva)) {
            $items[] = $this->issue($site, $row, 'high', 82, 'site_missing_power', 'Survey/location power data is missing.', $mainContractorOwner, 'Complete power_kva on the site before document/report work.');
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
            'construction_missing_wo' => 'blocked',
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
