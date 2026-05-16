<?php

namespace App\Services\Dashboard;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectControlTowerService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): array
    {
        $priorityQueue = $this->priorityQueue($user, $mainContractorFilter, $projectFilter);
        $forecast = $this->completionForecast($user, $mainContractorFilter, $projectFilter);

        return [
            'generated_at' => now()->toIso8601String(),
            'metrics' => [
                'critical_actions' => $priorityQueue->where('severity', 'critical')->count(),
                'projects_at_risk' => $forecast->whereIn('risk_level', ['at_risk', 'late', 'stalled'])->count(),
                'ready_for_report' => $this->readyForReportCount($user, $mainContractorFilter, $projectFilter),
                'stalled' => $priorityQueue->where('type', 'stalled_assignment')->count(),
            ],
            'priority_queue' => $priorityQueue->take(12)->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function completionForecast(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): Collection
    {
        $fourWeeksAgo = now()->subWeeks(4);

        $recentCompletions = DB::table('assignments')
            ->join('sites', 'sites.id', '=', 'assignments.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->tap(fn (Builder $query) => $this->applyTenantScope($query, $user, $mainContractorFilter))
            ->when($projectFilter, fn (Builder $query) => $query->where('projects.id', $projectFilter))
            ->whereIn('assignments.status', [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value])
            ->whereNotNull('assignments.verified_at')
            ->where('assignments.verified_at', '>=', $fourWeeksAgo)
            ->select('sites.project_id', DB::raw('count(*) as completed_last_4w'))
            ->groupBy('sites.project_id')
            ->pluck('completed_last_4w', 'project_id');

        $assignmentRows = DB::table('assignments')
            ->join('sites', 'sites.id', '=', 'assignments.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->leftJoin('assignment_construction_data', 'assignment_construction_data.assignment_id', '=', 'assignments.id')
            ->tap(fn (Builder $query) => $this->applyTenantScope($query, $user, $mainContractorFilter))
            ->when($projectFilter, fn (Builder $query) => $query->where('projects.id', $projectFilter))
            ->whereNotIn('assignments.status', [
                AssignmentStatus::Verified->value,
                AssignmentStatus::Reported->value,
                AssignmentStatus::Drop->value,
            ])
            ->select([
                'projects.id as project_id',
                'assignments.id',
                'assignments.activity_type',
                'assignments.status',
                'assignments.updated_at',
                'sites.power_kva',
                'assignment_construction_data.cons_wo_number',
            ])
            ->get()
            ->groupBy('project_id');

        $projects = DB::table('projects')
            ->tap(fn (Builder $query) => $this->applyTenantScope($query, $user, $mainContractorFilter))
            ->when($projectFilter, fn (Builder $query) => $query->where('projects.id', $projectFilter))
            ->select('id', 'name', 'end_date')
            ->get();

        return $projects->map(function ($project) use ($recentCompletions, $assignmentRows): array {
            $projectAssignments = $assignmentRows->get($project->id, collect());
            $remainingCount = $projectAssignments->count();
            $completedLast4w = (int) ($recentCompletions[$project->id] ?? 0);
            $weeklyRate = round($completedLast4w / 4, 1);
            $endDate = $project->end_date ? Carbon::parse($project->end_date) : null;

            $projectedFinish = null;
            $weeksToFinish = null;
            $onTrack = null;
            $delayDays = null;

            if ($remainingCount === 0) {
                $projectedFinish = 'Done';
                $onTrack = true;
                $delayDays = 0;
            } elseif ($weeklyRate > 0) {
                $weeksToFinish = (int) ceil($remainingCount / $weeklyRate);
                $projectedDate = now()->addWeeks($weeksToFinish);
                $projectedFinish = $projectedDate->format('d M Y');
                $onTrack = $endDate ? $projectedDate->lte($endDate) : null;
                $delayDays = $endDate ? max(0, (int) $endDate->diffInDays($projectedDate, false)) : null;
            }

            $blockers = $this->projectBlockers($projectAssignments);
            $mainCause = $this->mainDelayCause($blockers, $remainingCount, $weeklyRate);
            $confidence = $this->forecastConfidence($remainingCount, $weeklyRate, $blockers['total']);
            $riskLevel = match (true) {
                $remainingCount === 0 => 'done',
                $weeklyRate <= 0 => 'stalled',
                $onTrack === false => 'late',
                $blockers['total'] > 0 => 'at_risk',
                default => 'on_track',
            };

            return [
                'id' => $project->id,
                'name' => $project->name,
                'remaining' => $remainingCount,
                'weekly_rate' => $weeklyRate,
                'weeks_to_finish' => $weeksToFinish,
                'projected_finish' => $projectedFinish,
                'end_date' => $endDate?->format('d M Y'),
                'on_track' => $onTrack,
                'risk_level' => $riskLevel,
                'delay_days' => $delayDays,
                'confidence' => $confidence,
                'blocker_count' => $blockers['total'],
                'main_cause' => $mainCause,
                'recommended_action' => $this->forecastAction($blockers, $weeklyRate, $remainingCount),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function priorityQueue(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): Collection
    {
        $rows = DB::table('assignments')
            ->join('sites', 'sites.id', '=', 'assignments.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->leftJoin('subcontractors', 'subcontractors.id', '=', 'assignments.subcontractor_id')
            ->leftJoin('assignment_construction_data', 'assignment_construction_data.assignment_id', '=', 'assignments.id')
            ->tap(fn (Builder $query) => $this->applyTenantScope($query, $user, $mainContractorFilter))
            ->when($projectFilter, fn (Builder $query) => $query->where('projects.id', $projectFilter))
            ->whereNotIn('assignments.status', [AssignmentStatus::Drop->value, AssignmentStatus::Reported->value])
            ->select([
                'assignments.id',
                'assignments.site_id',
                'assignments.activity_type',
                'assignments.status',
                'assignments.updated_at',
                'sites.project_id',
                'sites.site_code',
                'sites.location_name',
                'sites.power_kva',
                'projects.name as project_name',
                'subcontractors.name as subcontractor_name',
                'assignment_construction_data.cons_wo_number',
            ])
            ->get();

        return $rows
            ->flatMap(fn ($row): array => $this->queueItemsForAssignment($row))
            ->sortByDesc('severity_score')
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queueItemsForAssignment(object $row): array
    {
        $items = [];
        $ageDays = (int) Carbon::parse($row->updated_at)->diffInDays(now());

        if (
            $row->activity_type === ActivityType::Construction->value
            && blank($row->cons_wo_number)
            && ! in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value], true)
        ) {
            $items[] = $this->queueItem($row, 'critical', 95, 'construction_missing_wo', 'Admin', 'Construction missing WO number', 'Fill WO number so construction can proceed.');
        }

        if (
            $row->activity_type === ActivityType::Survey->value
            && blank($row->power_kva)
        ) {
            $items[] = $this->queueItem($row, 'high', 80, 'site_missing_power', 'Admin', 'Survey site power data is missing', 'Complete site power_kva before document/report work.');
        }

        if ($row->status === AssignmentStatus::Verified->value) {
            $items[] = $this->queueItem($row, 'medium', 55, 'verified_not_reported', 'Super Admin', 'Verified assignment has not been reported', 'Include this assignment in the next report generation.');
        }

        if (
            in_array($row->status, [AssignmentStatus::Pending->value, AssignmentStatus::Revision->value], true)
            && $ageDays >= 7
        ) {
            $severity = $ageDays >= 14 ? 'critical' : 'high';
            $score = $ageDays >= 14 ? 90 : 72;
            $owner = $row->status === AssignmentStatus::Revision->value ? ($row->subcontractor_name ?? 'Subcon') : 'Admin';
            $items[] = $this->queueItem($row, $severity, $score, 'stalled_assignment', $owner, "{$row->status} for {$ageDays} days", 'Follow up the owner and update progress or revision response.');
        }

        if (in_array($row->status, array_map(fn (AssignmentStatus $status): string => $status->value, AssignmentStatus::verifiableStatuses()), true)) {
            $items[] = $this->queueItem($row, 'medium', 50, 'ready_for_report_review', 'Super Admin', 'Assignment is ready for verification/report flow', 'Review data quality and verify or generate report when valid.');
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function queueItem(object $row, string $severity, int $score, string $type, string $owner, string $problem, string $recommendedAction): array
    {
        return [
            'severity' => $severity,
            'severity_score' => $score,
            'type' => $type,
            'project_id' => (int) $row->project_id,
            'project' => $row->project_name,
            'site_id' => (int) $row->site_id,
            'site_code' => $row->site_code,
            'site_name' => $row->location_name,
            'assignment_id' => (int) $row->id,
            'activity_type' => $row->activity_type,
            'status' => $row->status,
            'owner' => $owner,
            'problem' => $problem,
            'recommended_action' => $recommendedAction,
            'url' => route('admin.assignments.show', $row->id),
            'ai_prompt' => "Kenapa assignment {$row->site_code} {$row->activity_type} bermasalah dan apa tindakan terbaiknya?",
        ];
    }

    private function readyForReportCount(User $user, ?int $mainContractorFilter = null, ?int $projectFilter = null): int
    {
        return (int) DB::table('assignments')
            ->join('sites', 'sites.id', '=', 'assignments.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->tap(fn (Builder $query) => $this->applyTenantScope($query, $user, $mainContractorFilter))
            ->when($projectFilter, fn (Builder $query) => $query->where('projects.id', $projectFilter))
            ->whereIn('assignments.status', array_map(fn (AssignmentStatus $status): string => $status->value, AssignmentStatus::verifiableStatuses()))
            ->count();
    }

    /**
     * @param  Collection<int, object>  $assignments
     * @return array{construction_missing_wo: int, site_missing_power: int, stale: int, revision: int, total: int}
     */
    private function projectBlockers(Collection $assignments): array
    {
        $constructionMissingWo = $assignments->filter(
            fn ($row): bool => $row->activity_type === ActivityType::Construction->value && blank($row->cons_wo_number)
        )->count();
        $siteMissingPower = $assignments->filter(
            fn ($row): bool => $row->activity_type === ActivityType::Survey->value && blank($row->power_kva)
        )->count();
        $stale = $assignments->filter(
            fn ($row): bool => Carbon::parse($row->updated_at)->lte(now()->subDays(7))
        )->count();
        $revision = $assignments->where('status', AssignmentStatus::Revision->value)->count();

        return [
            'construction_missing_wo' => $constructionMissingWo,
            'site_missing_power' => $siteMissingPower,
            'stale' => $stale,
            'revision' => $revision,
            'total' => $constructionMissingWo + $siteMissingPower + $stale + $revision,
        ];
    }

    /**
     * @param  array{construction_missing_wo: int, site_missing_power: int, stale: int, revision: int, total: int}  $blockers
     */
    private function mainDelayCause(array $blockers, int $remainingCount, float $weeklyRate): string
    {
        if ($remainingCount === 0) {
            return 'All active assignments completed.';
        }

        if ($weeklyRate <= 0) {
            return 'No verified progress in the last 4 weeks.';
        }

        if ($blockers['construction_missing_wo'] > 0) {
            return "{$blockers['construction_missing_wo']} construction assignments missing WO.";
        }

        if ($blockers['site_missing_power'] > 0) {
            return "{$blockers['site_missing_power']} survey assignments missing power data.";
        }

        if ($blockers['revision'] > 0) {
            return "{$blockers['revision']} assignments are in revision.";
        }

        if ($blockers['stale'] > 0) {
            return "{$blockers['stale']} assignments have no update for 7+ days.";
        }

        return 'Current pace is the primary forecast driver.';
    }

    /**
     * @param  array{construction_missing_wo: int, site_missing_power: int, stale: int, revision: int, total: int}  $blockers
     */
    private function forecastAction(array $blockers, float $weeklyRate, int $remainingCount): string
    {
        if ($remainingCount === 0) {
            return 'No action needed.';
        }

        if ($blockers['construction_missing_wo'] > 0) {
            return 'Complete missing WO numbers before construction follow-up.';
        }

        if ($blockers['site_missing_power'] > 0) {
            return 'Complete site power data to unblock survey documents.';
        }

        if ($blockers['revision'] > 0) {
            return 'Close revision comments with the assigned subcontractor.';
        }

        if ($weeklyRate <= 0) {
            return 'Review assignment owners and force a progress update this week.';
        }

        return 'Monitor pace and resolve stale assignments first.';
    }

    private function forecastConfidence(int $remainingCount, float $weeklyRate, int $blockerCount): string
    {
        if ($remainingCount === 0) {
            return 'high';
        }

        if ($weeklyRate <= 0 || $blockerCount >= 10) {
            return 'low';
        }

        if ($weeklyRate < 2 || $blockerCount > 0) {
            return 'medium';
        }

        return 'high';
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
