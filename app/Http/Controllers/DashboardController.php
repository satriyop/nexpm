<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\User;
use App\Services\Dashboard\ProjectControlTowerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProjectControlTowerService $controlTower): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isDrafter()) {
            return redirect()->route('drafter.assignments.index');
        }

        $mainContractorFilter = $user->isSuperAdmin() && $request->filled('main_contractor_id')
            ? $request->integer('main_contractor_id')
            : null;

        $projectFilter = $user->isSuperAdmin() && $request->filled('project_id')
            ? $request->integer('project_id')
            : null;

        $applyTenantScope = function ($q) use ($user, $mainContractorFilter): void {
            if (! $user->isSuperAdmin()) {
                $q->where('projects.main_contractor_id', $user->main_contractor_id);
            } elseif ($mainContractorFilter) {
                $q->where('projects.main_contractor_id', $mainContractorFilter);
            }
        };

        return Inertia::render('Dashboard', [
            'controlTower' => Inertia::defer(fn () => $controlTower->build($user, $mainContractorFilter, $projectFilter)),

            'deadlineRisk' => Inertia::defer(function () use ($projectFilter, $applyTenantScope) {
                $today = now()->startOfDay();

                $rows = DB::table('projects')
                    ->leftJoin('sites', 'sites.project_id', '=', 'projects.id')
                    ->leftJoin('assignments', function ($join) {
                        $join->on('assignments.site_id', '=', 'sites.id')
                            ->where('assignments.status', '!=', 'DROP');
                    })
                    ->tap(fn ($q) => $applyTenantScope($q))
                    ->when($projectFilter, fn ($q) => $q->where('projects.id', $projectFilter))
                    ->select(
                        'projects.id',
                        'projects.name',
                        'projects.start_date',
                        'projects.end_date',
                        'assignments.status',
                        DB::raw('count(assignments.id) as total')
                    )
                    ->groupBy('projects.id', 'projects.name', 'projects.start_date', 'projects.end_date', 'assignments.status')
                    ->get();

                return $rows->groupBy('id')->map(function ($projectRows) use ($today) {
                    $first = $projectRows->first();
                    $startDate = $first->start_date ? Carbon::parse($first->start_date)->startOfDay() : null;
                    $endDate = $first->end_date ? Carbon::parse($first->end_date)->startOfDay() : null;

                    $total = (int) $projectRows->sum('total');
                    $completed = (int) $projectRows->whereIn('status', ['VERIFIED', 'REPORTED'])->sum('total');
                    $completionPct = $total > 0 ? (int) round($completed / $total * 100) : 0;

                    $daysElapsed = $startDate ? max(0, (int) $startDate->diffInDays($today, false)) : null;
                    $daysRemaining = $endDate ? (int) $endDate->diffInDays($today, false) : null;
                    $daysTotal = ($startDate && $endDate) ? (int) $startDate->diffInDays($endDate, false) : null;
                    $expectedPct = ($daysTotal !== null && $daysTotal > 0 && $daysElapsed !== null)
                        ? (int) min(100, round($daysElapsed / $daysTotal * 100))
                        : null;
                    $gap = ($expectedPct !== null) ? ($completionPct - $expectedPct) : null;

                    $riskLevel = match (true) {
                        $endDate === null => 'no_deadline',
                        $daysRemaining !== null && $daysRemaining < 0 && $completionPct < 100 => 'overdue',
                        $gap === null => 'no_deadline',
                        $gap >= -5 => 'on_track',
                        $gap >= -20 => 'at_risk',
                        default => 'behind',
                    };

                    return [
                        'id' => $first->id,
                        'name' => $first->name,
                        'total' => $total,
                        'completed' => $completed,
                        'completion_pct' => $completionPct,
                        'days_elapsed' => $daysElapsed,
                        'days_remaining' => $daysRemaining,
                        'expected_pct' => $expectedPct,
                        'gap' => $gap,
                        'risk_level' => $riskLevel,
                    ];
                })->values()->all();
            }),

            'statusCounts' => Inertia::defer(fn () => DB::table('assignments')
                ->join('sites', 'sites.id', '=', 'assignments.site_id')
                ->join('projects', 'projects.id', '=', 'sites.project_id')
                ->tap($applyTenantScope)
                ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                ->select('assignments.status', DB::raw('count(*) as total'))
                ->groupBy('assignments.status')
                ->pluck('total', 'status')
                ->all()),

            'activityMatrix' => Inertia::defer(fn () => DB::table('assignments')
                ->join('sites', 'sites.id', '=', 'assignments.site_id')
                ->join('projects', 'projects.id', '=', 'sites.project_id')
                ->tap($applyTenantScope)
                ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                ->select('assignments.activity_type', 'assignments.status', DB::raw('count(*) as total'))
                ->groupBy('assignments.activity_type', 'assignments.status')
                ->get()
                ->groupBy('activity_type')
                ->map(fn ($rows) => $rows->pluck('total', 'status')->all())
                ->all()),

            'projectBreakdowns' => Inertia::defer(fn () => DB::table('projects')
                ->leftJoin('sites', 'sites.project_id', '=', 'projects.id')
                ->leftJoin('assignments', 'assignments.site_id', '=', 'sites.id')
                ->tap(fn ($q) => $applyTenantScope($q))
                ->when($projectFilter, fn ($q) => $q->where('projects.id', $projectFilter))
                ->select('projects.id', 'projects.name', 'assignments.status', DB::raw('count(assignments.id) as total'))
                ->groupBy('projects.id', 'projects.name', 'assignments.status')
                ->get()
                ->groupBy('id')
                ->map(function ($rows) {
                    $counts = [];
                    foreach ($rows as $row) {
                        if ($row->status !== null) {
                            $counts[$row->status] = $row->total;
                        }
                    }

                    return [
                        'id' => $rows->first()->id,
                        'name' => $rows->first()->name,
                        'counts' => $counts,
                    ];
                })
                ->values()
                ->all()),

            'recentActivity' => Inertia::defer(fn () => Assignment::query()
                ->with(['site', 'subcontractor'])
                ->whereHas('site.project', function ($q) use ($user, $mainContractorFilter): void {
                    if (! $user->isSuperAdmin()) {
                        $q->whereScopedToMainContractor();
                    } elseif ($mainContractorFilter) {
                        $q->where('main_contractor_id', $mainContractorFilter);
                    }
                })
                ->when($projectFilter, fn ($q) => $q->whereHas('site', fn ($sq) => $sq->where('project_id', $projectFilter)))
                ->latest('updated_at')
                ->limit(10)
                ->get(['id', 'site_id', 'subcontractor_id', 'activity_type', 'status', 'updated_at'])
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'activity_type' => $assignment->activity_type->value,
                    'status' => $assignment->status->value,
                    'updated_at' => $assignment->updated_at->toISOString(),
                    'site' => $assignment->site ? [
                        'site_code' => $assignment->site->site_code,
                        'location_name' => $assignment->site->location_name,
                    ] : null,
                    'subcontractor' => $assignment->subcontractor ? [
                        'name' => $assignment->subcontractor->name,
                    ] : null,
                ])
                ->all()),

            'activityChart' => Inertia::defer(function () use ($projectFilter, $applyTenantScope) {
                $activityTypes = ['SURVEY', 'CONSTRUCTION', 'PLN_CONNECTION', 'BAST'];
                $colors = [
                    'SURVEY' => 'rgba(14, 165, 233, 0.8)',
                    'CONSTRUCTION' => 'rgba(249, 115, 22, 0.8)',
                    'PLN_CONNECTION' => 'rgba(20, 184, 166, 0.8)',
                    'BAST' => 'rgba(139, 92, 246, 0.8)',
                ];

                $rows = DB::table('assignments')
                    ->join('sites', 'sites.id', '=', 'assignments.site_id')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->tap($applyTenantScope)
                    ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                    ->where('assignments.status', '!=', 'DROP')
                    ->select('projects.id', 'projects.name', 'assignments.activity_type', DB::raw('count(*) as total'))
                    ->groupBy('projects.id', 'projects.name', 'assignments.activity_type')
                    ->get();

                $projectNames = $rows->pluck('name', 'id')->unique()->values()->all();
                $projectIds = $rows->pluck('id')->unique()->values()->all();

                $grouped = $rows->groupBy('activity_type');

                $datasets = [];
                foreach ($activityTypes as $type) {
                    $countByProject = $grouped->get($type, collect())->pluck('total', 'id');
                    $datasets[] = [
                        'label' => ucwords(strtolower(str_replace('_', ' ', $type))),
                        'backgroundColor' => $colors[$type],
                        'data' => array_map(fn ($id) => (int) ($countByProject[$id] ?? 0), $projectIds),
                    ];
                }

                return ['labels' => $projectNames, 'datasets' => $datasets];
            }),

            'subcontractorLeaderboard' => Inertia::defer(function () use ($projectFilter, $applyTenantScope) {
                // Query 1: counts per subcontractor
                $counts = DB::table('subcontractors')
                    ->join('assignments', 'assignments.subcontractor_id', '=', 'subcontractors.id')
                    ->join('sites', 'sites.id', '=', 'assignments.site_id')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->tap($applyTenantScope)
                    ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                    ->where('assignments.status', '!=', 'DROP')
                    ->select(
                        'subcontractors.id',
                        'subcontractors.name',
                        DB::raw('count(assignments.id) as total'),
                        DB::raw("sum(case when assignments.status in ('VERIFIED','REPORTED') then 1 else 0 end) as completed"),
                        DB::raw("sum(case when assignments.status = 'REVISION' then 1 else 0 end) as in_revision")
                    )
                    ->groupBy('subcontractors.id', 'subcontractors.name')
                    ->get()
                    ->keyBy('id');

                // Query 2: cycle time data — pulled as raw timestamps, averaged in PHP (DB-agnostic)
                $cycleTimes = DB::table('assignments')
                    ->join('sites', 'sites.id', '=', 'assignments.site_id')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->tap($applyTenantScope)
                    ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                    ->whereNotNull('assignments.verified_at')
                    ->select('assignments.subcontractor_id', 'assignments.created_at', 'assignments.verified_at')
                    ->get()
                    ->groupBy('subcontractor_id')
                    ->map(fn ($rows) => (int) round(
                        $rows->avg(fn ($r) => Carbon::parse($r->verified_at)->diffInDays(Carbon::parse($r->created_at)))
                    ));

                // Query 3: historical revision events from audit log
                $revisionCounts = DB::table('assignment_audit_logs')
                    ->join('assignments', 'assignments.id', '=', 'assignment_audit_logs.assignment_id')
                    ->join('sites', 'sites.id', '=', 'assignments.site_id')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->tap($applyTenantScope)
                    ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                    ->where('assignment_audit_logs.event', 'revised')
                    ->select('assignments.subcontractor_id', DB::raw('count(*) as revision_count'))
                    ->groupBy('assignments.subcontractor_id')
                    ->pluck('revision_count', 'subcontractor_id');

                return $counts->map(function ($row) use ($cycleTimes, $revisionCounts) {
                    $total = (int) $row->total;
                    $completed = (int) $row->completed;
                    $revisions = (int) ($revisionCounts[$row->id] ?? 0);
                    $avgCycleDays = $cycleTimes[$row->id] ?? null;

                    $revisionRate = $total > 0 ? ($revisions / $total) : 0;
                    $score = 5;
                    if ($revisionRate > 0.40) {
                        $score -= 2;
                    } elseif ($revisionRate > 0.20) {
                        $score -= 1;
                    }
                    if ($avgCycleDays !== null && $avgCycleDays > 60) {
                        $score -= 2;
                    } elseif ($avgCycleDays !== null && $avgCycleDays > 30) {
                        $score -= 1;
                    }
                    $score = max(1, $score);

                    return [
                        'id' => $row->id,
                        'name' => $row->name,
                        'total' => $total,
                        'completed' => $completed,
                        'completion_pct' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                        'avg_cycle_days' => $avgCycleDays,
                        'revision_count' => $revisions,
                        'in_revision' => (int) $row->in_revision,
                        'score' => $score,
                    ];
                })
                    ->sortByDesc('score')
                    ->sortByDesc('completion_pct')
                    ->values()
                    ->all();
            }),

            'velocityTrend' => Inertia::defer(function () use ($projectFilter, $applyTenantScope) {
                $twelveWeeksAgo = now()->subWeeks(12)->startOfWeek();

                $timestamps = DB::table('assignments')
                    ->join('sites', 'sites.id', '=', 'assignments.site_id')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->tap($applyTenantScope)
                    ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                    ->whereIn('assignments.status', ['VERIFIED', 'REPORTED'])
                    ->whereNotNull('assignments.verified_at')
                    ->where('assignments.verified_at', '>=', $twelveWeeksAgo)
                    ->pluck('assignments.verified_at');

                $byWeek = $timestamps->groupBy(
                    fn ($ts) => Carbon::parse($ts)->startOfWeek()->format('Y-m-d')
                )->map->count();

                $weeks = [];
                for ($i = 11; $i >= 0; $i--) {
                    $weekStart = now()->subWeeks($i)->startOfWeek()->format('Y-m-d');
                    $weeks[] = [
                        'week_start' => $weekStart,
                        'label' => Carbon::parse($weekStart)->format('d M'),
                        'count' => (int) ($byWeek[$weekStart] ?? 0),
                    ];
                }

                $thisWeek = $weeks[11]['count'];
                $lastWeek = $weeks[10]['count'];
                $recentFour = array_slice($weeks, 8);
                $fourWeekAvg = round(array_sum(array_column($recentFour, 'count')) / 4, 1);

                return [
                    'weeks' => $weeks,
                    'this_week' => $thisWeek,
                    'last_week' => $lastWeek,
                    'delta' => $thisWeek - $lastWeek,
                    'four_week_avg' => $fourWeekAvg,
                ];
            }),

            'agingHeatmap' => Inertia::defer(function () use ($projectFilter, $applyTenantScope) {
                $now = now();

                $rows = DB::table('assignments')
                    ->join('sites', 'sites.id', '=', 'assignments.site_id')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->tap($applyTenantScope)
                    ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                    ->whereNotIn('assignments.status', ['VERIFIED', 'REPORTED', 'DROP'])
                    ->select('assignments.status', 'assignments.updated_at')
                    ->get();

                $buckets = [
                    '0_7' => ['label' => '0–7d',   'min' => 0,  'max' => 7],
                    '7_14' => ['label' => '7–14d',  'min' => 7,  'max' => 14],
                    '14_30' => ['label' => '14–30d', 'min' => 14, 'max' => 30],
                    '30_60' => ['label' => '30–60d', 'min' => 30, 'max' => 60],
                    '60p' => ['label' => '60+d',   'min' => 60, 'max' => PHP_INT_MAX],
                ];

                $statusOrder = [
                    'PENDING', 'SURVEY', 'DOCUMENT', 'CONSTRUCTION', 'MACHINE_ONSITE',
                    'DONE', 'LIVE', 'REGISTRATION', 'BILLING', 'CONNECTION',
                    'KWH_DONE', 'SUBMITTED', 'REVISION',
                ];

                $cells = [];
                foreach ($rows as $row) {
                    $days = (int) Carbon::parse($row->updated_at)->diffInDays($now);
                    foreach ($buckets as $key => $bucket) {
                        if ($days >= $bucket['min'] && $days < $bucket['max']) {
                            $cells[$row->status][$key] = ($cells[$row->status][$key] ?? 0) + 1;
                            break;
                        }
                    }
                }

                $activeStatuses = array_values(array_filter($statusOrder, fn ($s) => isset($cells[$s])));
                $totalStalled = $rows->filter(
                    fn ($r) => (int) Carbon::parse($r->updated_at)->diffInDays($now) >= 30
                )->count();

                return [
                    'statuses' => $activeStatuses,
                    'buckets' => array_map(fn ($b) => $b['label'], $buckets),
                    'bucket_keys' => array_keys($buckets),
                    'cells' => $cells,
                    'total_stalled' => $totalStalled,
                ];
            }),

            'workloadDistribution' => Inertia::defer(function () use ($projectFilter, $applyTenantScope) {
                $inProgressStatuses = [
                    'SURVEY', 'DOCUMENT', 'CONSTRUCTION', 'MACHINE_ONSITE', 'DONE', 'LIVE',
                    'REGISTRATION', 'BILLING', 'CONNECTION', 'KWH_DONE', 'SUBMITTED',
                ];

                $rows = DB::table('assignments')
                    ->join('sites', 'sites.id', '=', 'assignments.site_id')
                    ->join('projects', 'projects.id', '=', 'sites.project_id')
                    ->join('subcontractors', 'subcontractors.id', '=', 'assignments.subcontractor_id')
                    ->tap($applyTenantScope)
                    ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
                    ->where('assignments.status', '!=', 'DROP')
                    ->select(
                        'subcontractors.id',
                        'subcontractors.name',
                        'assignments.status',
                        DB::raw('count(*) as total')
                    )
                    ->groupBy('subcontractors.id', 'subcontractors.name', 'assignments.status')
                    ->get()
                    ->groupBy('id');

                return $rows->map(function ($subRows) use ($inProgressStatuses) {
                    $first = $subRows->first();
                    $byStatus = $subRows->pluck('total', 'status');
                    $pending = (int) ($byStatus['PENDING'] ?? 0);
                    $inProgress = (int) collect($inProgressStatuses)->sum(fn ($s) => $byStatus[$s] ?? 0);
                    $revision = (int) ($byStatus['REVISION'] ?? 0);
                    $completed = (int) (($byStatus['VERIFIED'] ?? 0) + ($byStatus['REPORTED'] ?? 0));

                    return [
                        'id' => $first->id,
                        'name' => $first->name,
                        'pending' => $pending,
                        'in_progress' => $inProgress,
                        'revision' => $revision,
                        'completed' => $completed,
                        'total' => $pending + $inProgress + $revision + $completed,
                    ];
                })
                    ->sortByDesc('total')
                    ->take(20)
                    ->values()
                    ->all();
            }),

            'completionForecast' => Inertia::defer(fn () => $controlTower->completionForecast($user, $mainContractorFilter, $projectFilter)->all()),

            'mainContractors' => $user->isSuperAdmin()
                ? MainContractor::query()->orderBy('name')->get(['id', 'name'])
                : null,
            'projects' => $user->isSuperAdmin()
                ? Project::query()
                    ->when($mainContractorFilter, fn ($q) => $q->where('main_contractor_id', $mainContractorFilter))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : null,
            'filters' => (object) $request->only(['main_contractor_id', 'project_id']),
        ]);
    }
}
