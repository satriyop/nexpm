<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MainContractor;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 15;

        return Inertia::render('admin/projects/Index', [
            'projects' => Project::query()->whereScopedToMainContractor()->with(['mainContractor', 'client'])->latest('id')->paginate($perPage)->withQueryString(),
            'per_page' => $perPage,
            'mainContractors' => MainContractor::query()
                ->when(! $this->currentUser()->isGlobalAdmin(), fn ($query) => $query->whereKey($this->currentUser()->main_contractor_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'clients' => Client::query()->whereScopedToMainContractor()->with('mainContractors')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $mainContractorId = $user->isGlobalAdmin()
            ? $request->integer('main_contractor_id')
            : $user->main_contractor_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'main_contractor_id' => [$user->isGlobalAdmin() ? 'required' : 'nullable', 'exists:main_contractors,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['main_contractor_id'] = $mainContractorId;

        $project = Project::query()->create($validated);

        return redirect()->route('admin.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->ensureCanAccessProject($project);

        $project->load(['mainContractor', 'client']);

        return Inertia::render('admin/projects/Show', [
            'project' => $project,
            'sites' => $project->sites()->with('siteType')->latest('id')->paginate(25),
            'import' => session('import'),
        ]);
    }

    public function destroy(Project $project): RedirectResponse
    {
        abort_unless($this->currentUser()->isSuperAdmin(), 403);

        $projectName = $project->name;

        $this->deleteProjectFiles($project);

        // Delete orphaned reports before the cascade removes the pivot rows.
        $assignmentIds = DB::table('assignments')
            ->whereIn('site_id', DB::table('sites')->where('project_id', $project->id)->pluck('id'))
            ->pluck('id');

        if ($assignmentIds->isNotEmpty()) {
            $reportIds = DB::table('report_assignments')
                ->whereIn('assignment_id', $assignmentIds)
                ->pluck('report_id');

            if ($reportIds->isNotEmpty()) {
                DB::table('reports')->whereIn('id', $reportIds)->delete();
            }
        }

        $project->delete();

        return redirect()->route('admin.projects.index')
            ->with('success', "Project \"{$projectName}\" and all related data have been deleted.");
    }

    private function deleteProjectFiles(Project $project): void
    {
        $siteIds = DB::table('sites')->where('project_id', $project->id)->pluck('id');

        if ($siteIds->isEmpty()) {
            return;
        }

        $assignmentIds = DB::table('assignments')->whereIn('site_id', $siteIds)->pluck('id');

        $paths = collect();

        // Site photos
        $paths->push(...DB::table('site_photos')->whereIn('site_id', $siteIds)->pluck('path'));

        if ($assignmentIds->isNotEmpty()) {
            // Survey data file/photo columns
            $surveyRows = DB::table('assignment_survey_data')
                ->whereIn('assignment_id', $assignmentIds)
                ->get(['photo_overall_site', 'photo_parking_evcs', 'photo_access_route', 'photo_pln_network', 'photo_satellite_gmaps', 'file_mockup_3d', 'file_site_plan', 'file_ba_survey']);

            foreach ($surveyRows as $row) {
                foreach ((array) $row as $val) {
                    if ($val) {
                        $paths->push($val);
                    }
                }
            }

            // PLN data file columns
            $plnRows = DB::table('assignment_pln_data')
                ->whereIn('assignment_id', $assignmentIds)
                ->get(['file_slo', 'file_nidi', 'file_reg', 'file_pk', 'foto_kwh']);

            foreach ($plnRows as $row) {
                foreach ((array) $row as $val) {
                    if ($val) {
                        $paths->push($val);
                    }
                }
            }

            // Construction: single foto_machine_sn column + construction photos
            $consDataIds = DB::table('assignment_construction_data')
                ->whereIn('assignment_id', $assignmentIds)
                ->get(['id', 'foto_machine_sn']);

            foreach ($consDataIds as $row) {
                if ($row->foto_machine_sn) {
                    $paths->push($row->foto_machine_sn);
                }
            }

            $consDataIdList = $consDataIds->pluck('id');
            if ($consDataIdList->isNotEmpty()) {
                $paths->push(...DB::table('assignment_construction_photos')->whereIn('assignment_construction_data_id', $consDataIdList)->pluck('path'));
            }

            // BAST photos
            $bastDataIds = DB::table('assignment_bast_data')
                ->whereIn('assignment_id', $assignmentIds)
                ->pluck('id');

            if ($bastDataIds->isNotEmpty()) {
                $paths->push(...DB::table('assignment_bast_photos')->whereIn('assignment_bast_data_id', $bastDataIds)->pluck('photo_path'));
            }

            // Legacy report files
            $paths->push(...DB::table('assignment_legacy_reports')->whereIn('assignment_id', $assignmentIds)->pluck('file_path'));
        }

        $filesToDelete = $paths->filter()->unique()->values()->all();

        if ($filesToDelete !== []) {
            Storage::disk('public')->delete($filesToDelete);
        }
    }
}
