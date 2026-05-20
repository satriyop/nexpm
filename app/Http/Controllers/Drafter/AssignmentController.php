<?php

namespace App\Http\Controllers\Drafter;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SiteRowResource;
use App\Models\Assignment;
use App\Models\MachineType;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Site::query()
            ->with([
                'siteType',
                'machineType',
                'project',
                'assignments.subcontractor',
                'assignments.constructionData',
            ])
            ->whereHas('assignments');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('site_code', 'like', "%{$search}%")
                    ->orWhere('location_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%");
            });
        }

        $filteringByDrop = $request->input('status') === 'DROP';

        $query->whereHas('assignments', function ($q) use ($request, $filteringByDrop): void {
            if ($request->filled('status')) {
                $q->where('status', $request->string('status'));
            } elseif (! $filteringByDrop) {
                $q->where('status', '!=', 'DROP');
            }
            if ($request->filled('activity_type')) {
                $q->where('activity_type', $request->string('activity_type'));
            }
        });

        if ($request->filled('subcontractor_id')) {
            $query->whereHas('assignments', fn ($q) => $q->where('subcontractor_id', $request->integer('subcontractor_id')));
        }

        if ($request->filled('main_contractor_id')) {
            $query->whereHas('project', fn ($q) => $q->where('main_contractor_id', $request->integer('main_contractor_id')));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('machine_type_id')) {
            $query->where('machine_type_id', $request->integer('machine_type_id'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 20;

        $sites = $query->latest('id')->paginate($perPage)->withQueryString();

        $sites->setCollection(
            $sites->getCollection()->map(fn ($site) => (new SiteRowResource($site))->resolve())
        );

        $mainContractorFilter = $request->filled('main_contractor_id')
            ? $request->integer('main_contractor_id')
            : null;

        $subcontractors = Subcontractor::query()
            ->when($mainContractorFilter, fn ($q) => $q->whereHas('mainContractors', fn ($query) => $query->whereKey($mainContractorFilter)))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $projects = Project::query()
            ->when($mainContractorFilter, fn ($q) => $q->where('main_contractor_id', $mainContractorFilter))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('drafter/assignments/Index', [
            'sites' => $sites,
            'subcontractors' => $subcontractors,
            'projects' => $projects,
            'machineTypes' => MachineType::query()->orderBy('name')->get(['id', 'name']),
            'mainContractors' => MainContractor::query()->orderBy('name')->get(['id', 'name']),
            'per_page' => $perPage,
            'filters' => (object) $request->only(['search', 'status', 'activity_type', 'subcontractor_id', 'main_contractor_id', 'project_id', 'machine_type_id']),
        ]);
    }

    public function show(Assignment $assignment): Response
    {
        $assignment->load([
            'site.siteType',
            'site.machineType',
            'site.project.mainContractor',
            'site.project.client',
            'subcontractor',
            'surveyData',
        ]);

        return Inertia::render('drafter/assignments/Show', [
            'assignment' => $assignment,
        ]);
    }
}
