<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MainContractor;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/projects/Index', [
            'projects'        => Project::query()->whereScopedToMainContractor()->with(['mainContractor', 'client'])->latest('id')->paginate(15),
            'mainContractors' => MainContractor::query()->orderBy('name')->get(['id', 'name']),
            'clients'         => Client::query()->whereScopedToMainContractor()->orderBy('name')->get(['id', 'name', 'main_contractor_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'main_contractor_id'  => ['required', 'exists:main_contractors,id'],
            'client_id'           => ['required', 'exists:clients,id'],
            'start_date'          => ['nullable', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget'              => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::query()->create($validated);

        return redirect()->route('admin.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, Project $project): Response
    {
        $project->load(['mainContractor', 'client']);

        return Inertia::render('admin/projects/Show', [
            'project' => $project,
            'sites'   => $project->sites()->with('siteType')->latest('id')->paginate(25),
            'import'  => session('import'),
        ]);
    }
}
