<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MachineType;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = Project::query()
            ->whereScopedToMainContractor()
            ->orderBy('name')
            ->get(['id', 'name']);

        $machineTypes = MachineType::orderBy('name')->get(['id', 'name']);

        $provinces = Site::query()
            ->whereNotNull('province')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('project', fn ($q) => $q->whereScopedToMainContractor())
            ->distinct()
            ->orderBy('province')
            ->pluck('province');

        $query = Site::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('project', fn ($q) => $q->whereScopedToMainContractor());

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('province')) {
            $query->where('province', $request->string('province'));
        }

        if ($request->filled('machine_type_id')) {
            $query->where('machine_type_id', $request->integer('machine_type_id'));
        }

        if ($request->filled('activity_type') || $request->filled('status') || $request->filled('subcontractor_id')) {
            $query->whereHas('assignments', function ($q) use ($request): void {
                if ($request->filled('activity_type')) {
                    $q->where('activity_type', $request->string('activity_type'));
                }
                if ($request->filled('status')) {
                    $q->where('status', $request->string('status'));
                }
                if ($request->filled('subcontractor_id')) {
                    $q->where('subcontractor_id', $request->integer('subcontractor_id'));
                }
            });
        }

        $sites = $query
            ->with([
                'project:id,name',
                'machineType:id,name',
                'photos:id,site_id,path',
                'assignments:id,site_id,activity_type,status,subcontractor_id',
                'assignments.subcontractor:id,name',
            ])
            ->get(['id', 'site_code', 'location_name', 'address', 'city', 'province', 'latitude', 'longitude', 'project_id', 'machine_type_id']);

        $assignmentIds = $sites->flatMap(fn ($s) => $s->assignments->pluck('id'))->unique();

        $surveyPhotos = DB::table('assignment_survey_data')
            ->whereIn('assignment_id', $assignmentIds)
            ->get(['assignment_id', 'photo_overall_site', 'photo_parking_evcs', 'photo_access_route', 'photo_pln_network', 'photo_satellite_gmaps'])
            ->keyBy('assignment_id');

        $constructionDataByAssignment = DB::table('assignment_construction_data')
            ->whereIn('assignment_id', $assignmentIds)
            ->pluck('id', 'assignment_id');

        $constructionPhotos = DB::table('assignment_construction_photos')
            ->whereIn('assignment_construction_data_id', $constructionDataByAssignment->values())
            ->get(['assignment_construction_data_id', 'path'])
            ->groupBy('assignment_construction_data_id');

        $plnPhotos = DB::table('assignment_pln_data')
            ->whereIn('assignment_id', $assignmentIds)
            ->whereNotNull('foto_kwh')
            ->get(['assignment_id', 'foto_kwh'])
            ->keyBy('assignment_id');

        $bastDataByAssignment = DB::table('assignment_bast_data')
            ->whereIn('assignment_id', $assignmentIds)
            ->pluck('id', 'assignment_id');

        $bastPhotos = DB::table('assignment_bast_photos')
            ->whereIn('assignment_bast_data_id', $bastDataByAssignment->values())
            ->get(['assignment_bast_data_id', 'photo_path'])
            ->groupBy('assignment_bast_data_id');

        $siteIds = $sites->pluck('id');

        $subcontractorQuery = DB::table('assignments')
            ->whereIn('site_id', $siteIds)
            ->whereNotNull('subcontractor_id');

        if ($request->filled('activity_type')) {
            $subcontractorQuery->where('activity_type', $request->string('activity_type'));
        }
        if ($request->filled('status')) {
            $subcontractorQuery->where('status', $request->string('status'));
        }

        $subcontractorIds = $subcontractorQuery->distinct()->pluck('subcontractor_id');

        $subcontractors = Subcontractor::whereIn('id', $subcontractorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $mapSites = $sites->map(function (Site $site) use ($surveyPhotos, $constructionDataByAssignment, $constructionPhotos, $plnPhotos, $bastDataByAssignment, $bastPhotos): array {
            $activeAssignments = $site->assignments->filter(fn ($a) => $a->status->value !== 'DROP');
            $totalAssignments = $activeAssignments->count();
            $completedCount = $activeAssignments
                ->filter(fn ($a) => in_array($a->status->value, ['VERIFIED', 'REPORTED']))
                ->count();

            $photos = $this->buildPhotos($site, $surveyPhotos, $constructionDataByAssignment, $constructionPhotos, $plnPhotos, $bastDataByAssignment, $bastPhotos);

            $assignments = $site->assignments->map(fn ($a) => [
                'id' => $a->id,
                'activity_type' => $a->activity_type->value,
                'activity_label' => $a->activity_type->label(),
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'status_color' => $a->status->color(),
                'subcontractor' => $a->subcontractor
                    ? ['id' => $a->subcontractor->id, 'name' => $a->subcontractor->name]
                    : null,
            ])->values()->all();

            return [
                'id' => $site->id,
                'site_code' => $site->site_code,
                'location_name' => $site->location_name,
                'address' => $site->address,
                'city' => $site->city,
                'province' => $site->province,
                'latitude' => (float) $site->latitude,
                'longitude' => (float) $site->longitude,
                'project' => $site->project ? ['id' => $site->project->id, 'name' => $site->project->name] : null,
                'machine_type' => $site->machineType ? ['id' => $site->machineType->id, 'name' => $site->machineType->name] : null,
                'total_assignments' => $totalAssignments,
                'completed_count' => $completedCount,
                'completion_ratio' => $totalAssignments > 0 ? round($completedCount / $totalAssignments, 2) : 0.0,
                'photos' => $photos,
                'assignments' => $assignments,
            ];
        })->values()->all();

        $siteCollection = collect($mapSites);

        $stats = [
            'total_sites' => $siteCollection->count(),
            'completed_sites' => $siteCollection->filter(fn ($s) => $s['total_assignments'] > 0 && $s['completed_count'] === $s['total_assignments'])->count(),
            'in_progress_sites' => $siteCollection->filter(fn ($s) => $s['completed_count'] > 0 && $s['completed_count'] < $s['total_assignments'])->count(),
            'total_assignments' => $siteCollection->sum('total_assignments'),
        ];

        return Inertia::render('admin/Map', [
            'sites' => $mapSites,
            'projects' => $projects,
            'subcontractors' => $subcontractors,
            'machineTypes' => $machineTypes,
            'provinces' => $provinces,
            'stats' => $stats,
            'filters' => $request->only(['project_id', 'activity_type', 'status', 'subcontractor_id', 'machine_type_id', 'province']),
        ]);
    }

    /**
     * @param  Collection<int,object>  $surveyPhotos
     * @param  Collection<int,int>  $constructionDataByAssignment
     * @param  Collection<int,Collection<int,object>>  $constructionPhotos
     * @param  Collection<int,object>  $plnPhotos
     * @param  Collection<int,int>  $bastDataByAssignment
     * @param  Collection<int,Collection<int,object>>  $bastPhotos
     * @return list<array{label: string, url: string}>
     */
    private function buildPhotos(
        Site $site,
        $surveyPhotos,
        $constructionDataByAssignment,
        $constructionPhotos,
        $plnPhotos,
        $bastDataByAssignment,
        $bastPhotos,
    ): array {
        $photos = [];

        foreach ($site->photos as $photo) {
            $photos[] = ['label' => 'Site', 'url' => '/storage/'.$photo->path];
        }

        foreach ($site->assignments as $assignment) {
            $label = $assignment->activity_type->label();

            match ($assignment->activity_type->value) {
                'SURVEY' => (function () use (&$photos, $assignment, $surveyPhotos, $label): void {
                    $data = $surveyPhotos->get($assignment->id);
                    if (! $data) {
                        return;
                    }
                    foreach (['photo_overall_site', 'photo_parking_evcs', 'photo_access_route', 'photo_pln_network', 'photo_satellite_gmaps'] as $field) {
                        if (! empty($data->$field)) {
                            $photos[] = ['label' => $label, 'url' => '/storage/'.$data->$field];
                        }
                    }
                })(),

                'CONSTRUCTION' => (function () use (&$photos, $assignment, $constructionDataByAssignment, $constructionPhotos, $label): void {
                    $dataId = $constructionDataByAssignment->get($assignment->id);
                    if (! $dataId) {
                        return;
                    }
                    foreach ($constructionPhotos->get($dataId, collect()) as $photo) {
                        $photos[] = ['label' => $label, 'url' => '/storage/'.$photo->path];
                    }
                })(),

                'PLN_CONNECTION' => (function () use (&$photos, $assignment, $plnPhotos, $label): void {
                    $data = $plnPhotos->get($assignment->id);
                    if ($data && ! empty($data->foto_kwh)) {
                        $photos[] = ['label' => $label, 'url' => '/storage/'.$data->foto_kwh];
                    }
                })(),

                'BAST' => (function () use (&$photos, $assignment, $bastDataByAssignment, $bastPhotos, $label): void {
                    $dataId = $bastDataByAssignment->get($assignment->id);
                    if (! $dataId) {
                        return;
                    }
                    foreach ($bastPhotos->get($dataId, collect()) as $photo) {
                        $photos[] = ['label' => $label, 'url' => '/storage/'.$photo->photo_path];
                    }
                })(),

                default => null,
            };
        }

        return $photos;
    }
}
