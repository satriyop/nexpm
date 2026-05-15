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
            'subcontractors' => Subcontractor::query()->whereScopedToMainContractor()->with('mainContractors')->latest('id')->paginate($perPage)->withQueryString(),
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:subcontractors,code'],
            'main_contractor_ids' => [$user->isSuperAdmin() ? 'required' : 'nullable', 'array', 'min:1'],
            'main_contractor_ids.*' => ['required', 'integer', Rule::exists('main_contractors', 'id')],
            'pic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $mainContractorIds = $user->isSuperAdmin()
            ? $validated['main_contractor_ids']
            : [$user->main_contractor_id];

        $subcontractor = Subcontractor::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'pic' => $validated['pic'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);
        $subcontractor->mainContractors()->attach($mainContractorIds);

        return back()->with('success', 'Subcontractor created.');
    }

    public function update(Request $request, Subcontractor $subcontractor): RedirectResponse
    {
        $user = $this->currentUser();

        abort_if(
            ! $user->isSuperAdmin()
            && ! $subcontractor->mainContractors()->whereKey($user->main_contractor_id)->exists(),
            403
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('subcontractors', 'code')->ignore($subcontractor->id)],
            'main_contractor_ids' => [$user->isSuperAdmin() ? 'required' : 'nullable', 'array', 'min:1'],
            'main_contractor_ids.*' => ['required', 'integer', Rule::exists('main_contractors', 'id')],
            'pic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $subcontractor->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'pic' => $validated['pic'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);

        $mainContractorIds = $user->isSuperAdmin()
            ? $validated['main_contractor_ids']
            : [$user->main_contractor_id];

        $subcontractor->mainContractors()->sync($mainContractorIds);

        return back()->with('success', 'Subcontractor updated.');
    }

    public function destroy(Subcontractor $subcontractor): RedirectResponse
    {
        abort_if(
            ! $this->currentUser()->isSuperAdmin()
            && ! $subcontractor->mainContractors()->whereKey($this->currentUser()->main_contractor_id)->exists(),
            403
        );

        // Prevent deletion if related assignments exist
        if ($subcontractor->assignments()->exists()) {
            return back()->with('error', 'Cannot delete. Subcontractor has associated assignments.');
        }

        $subcontractor->delete();

        return back()->with('success', 'Subcontractor deleted.');
    }
}
