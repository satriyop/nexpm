<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

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
