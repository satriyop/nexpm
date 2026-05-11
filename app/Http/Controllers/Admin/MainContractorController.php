<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MainContractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MainContractorController extends Controller
{
    public function index(): Response
    {
        $user = $this->currentUser();

        return Inertia::render('admin/main-contractors/Index', [
            'mainContractors' => MainContractor::query()
                ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereKey($user->main_contractor_id))
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->currentUser()->isSuperAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
        ]);

        MainContractor::query()->create($validated);

        return back()->with('success', 'Main contractor created.');
    }

    public function update(Request $request, MainContractor $mainContractor): RedirectResponse
    {
        $this->ensureCanAccessMainContractor($mainContractor->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($mainContractor->logo) {
                Storage::disk('public')->delete($mainContractor->logo);
            }
            $validated['logo'] = $request->file('logo')->store('logos/contractors', 'public');
        } else {
            unset($validated['logo']);
        }

        $mainContractor->update($validated);

        return back()->with('success', 'Main contractor updated.');
    }
}
