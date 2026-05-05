<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\MainContractor;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var \App\Models\User $user */
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

        $statusCounts = DB::table('assignments')
            ->join('sites', 'sites.id', '=', 'assignments.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->tap($applyTenantScope)
            ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
            ->select('assignments.status', DB::raw('count(*) as total'))
            ->groupBy('assignments.status')
            ->pluck('total', 'status')
            ->all();

        $projectBreakdownRows = DB::table('projects')
            ->leftJoin('sites', 'sites.project_id', '=', 'projects.id')
            ->leftJoin('assignments', 'assignments.site_id', '=', 'sites.id')
            ->tap(fn ($q) => $applyTenantScope($q))
            ->when($projectFilter, fn ($q) => $q->where('projects.id', $projectFilter))
            ->select('projects.id', 'projects.name', 'assignments.status', DB::raw('count(assignments.id) as total'))
            ->groupBy('projects.id', 'projects.name', 'assignments.status')
            ->get();

        $projectBreakdowns = $projectBreakdownRows
            ->groupBy('id')
            ->map(function ($rows) {
                $counts = [];
                foreach ($rows as $row) {
                    if ($row->status !== null) {
                        $counts[$row->status] = $row->total;
                    }
                }

                return [
                    'id'     => $rows->first()->id,
                    'name'   => $rows->first()->name,
                    'counts' => $counts,
                ];
            })
            ->values()
            ->all();

        $activityMatrix = DB::table('assignments')
            ->join('sites', 'sites.id', '=', 'assignments.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->tap($applyTenantScope)
            ->when($projectFilter, fn ($q) => $q->where('sites.project_id', $projectFilter))
            ->select('assignments.activity_type', 'assignments.status', DB::raw('count(*) as total'))
            ->groupBy('assignments.activity_type', 'assignments.status')
            ->get()
            ->groupBy('activity_type')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->all())
            ->all();

        $recentActivity = Assignment::query()
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
                'id'            => $assignment->id,
                'activity_type' => $assignment->activity_type->value,
                'status'        => $assignment->status->value,
                'updated_at'    => $assignment->updated_at->toISOString(),
                'site'          => $assignment->site ? [
                    'site_code'     => $assignment->site->site_code,
                    'location_name' => $assignment->site->location_name,
                ] : null,
                'subcontractor' => $assignment->subcontractor ? [
                    'name' => $assignment->subcontractor->name,
                ] : null,
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'statusCounts'      => $statusCounts,
            'activityMatrix'    => $activityMatrix,
            'projectBreakdowns' => $projectBreakdowns,
            'recentActivity'    => $recentActivity,
            'mainContractors'   => $user->isSuperAdmin()
                ? MainContractor::query()->orderBy('name')->get(['id', 'name'])
                : null,
            'projects'          => $user->isSuperAdmin()
                ? Project::query()
                    ->when($mainContractorFilter, fn ($q) => $q->where('main_contractor_id', $mainContractorFilter))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : null,
            'filters'           => (object) $request->only(['main_contractor_id', 'project_id']),
        ]);
    }
}
