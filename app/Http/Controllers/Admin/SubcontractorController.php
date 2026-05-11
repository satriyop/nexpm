<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MainContractor;
use App\Models\Subcontractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubcontractorController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 15;

        return Inertia::render('admin/subcontractors/Index', [
            'subcontractors' => Subcontractor::query()->whereScopedToMainContractor()->with('mainContractor')->latest('id')->paginate($perPage)->withQueryString(),
            'per_page' => $perPage,
            'mainContractors' => MainContractor::query()
                ->when(! $this->currentUser()->isSuperAdmin(), fn ($query) => $query->whereKey($this->currentUser()->main_contractor_id))
                ->orderBy('name')
                ->get(['id', 'name']),
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
            'code' => ['required', 'string', 'max:50', 'unique:subcontractors,code'],
            'main_contractor_id' => [$user->isSuperAdmin() ? 'required' : 'nullable', Rule::exists('main_contractors', 'id')],
            'pic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $validated['main_contractor_id'] = $mainContractorId;

        Subcontractor::query()->create($validated);

        return back()->with('success', 'Subcontractor created.');
    }

    public function destroy(Subcontractor $subcontractor): RedirectResponse
    {
        $this->ensureCanAccessMainContractor($subcontractor->main_contractor_id);

        // Prevent deletion if related assignments exist
        if ($subcontractor->assignments()->exists()) {
            return back()->with('error', 'Cannot delete. Subcontractor has associated assignments.');
        }

        $subcontractor->delete();

        return back()->with('success', 'Subcontractor deleted.');
    }
}
