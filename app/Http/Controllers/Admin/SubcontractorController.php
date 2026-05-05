<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MainContractor;
use App\Models\Subcontractor;
use App\Models\SubcontractorType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubcontractorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/subcontractors/Index', [
            'subcontractors'      => Subcontractor::query()->whereScopedToMainContractor()->with('subcontractorType', 'mainContractor')->latest('id')->paginate(15),
            'subcontractorTypes'  => SubcontractorType::query()->orderBy('name')->get(['id', 'name']),
            'mainContractors'     => MainContractor::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'code'                   => ['required', 'string', 'max:50', 'unique:subcontractors,code'],
            'main_contractor_id'     => ['required', 'exists:main_contractors,id'],
            'subcontractor_type_id'  => ['required', 'exists:subcontractor_types,id'],
            'pic'                    => ['nullable', 'string', 'max:255'],
            'phone'                  => ['nullable', 'string', 'max:50'],
            'email'                  => ['nullable', 'email', 'max:255'],
        ]);

        Subcontractor::query()->create($validated);

        return back()->with('success', 'Subcontractor created.');
    }
}
