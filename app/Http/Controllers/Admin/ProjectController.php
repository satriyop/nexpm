<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MainContractor;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                ->when(! $this->currentUser()->isSuperAdmin(), fn ($query) => $query->whereKey($this->currentUser()->main_contractor_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'clients' => Client::query()->whereScopedToMainContractor()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        $mainContractorId = $user->isSuperAdmin()
            ? $request->integer('main_contractor_id')
            : $user->main_contractor_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'main_contractor_id' => [$user->isSuperAdmin() ? 'required' : 'nullable', 'exists:main_contractors,id'],
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')
                    ->where('main_contractor_id', $mainContractorId),
            ],
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
}
