<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MainContractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 15;

        return Inertia::render('admin/clients/Index', [
            'clients' => Client::query()->whereScopedToMainContractor()->with('mainContractor')->latest('id')->paginate($perPage)->withQueryString(),
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
            'main_contractor_id' => [$user->isSuperAdmin() ? 'required' : 'nullable', Rule::exists('main_contractors', 'id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['main_contractor_id'] = $mainContractorId;

        Client::query()->create($validated);

        return back()->with('success', 'Client created.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->ensureCanAccessMainContractor($client->main_contractor_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($client->logo) {
                Storage::disk('public')->delete($client->logo);
            }
            $validated['logo'] = $request->file('logo')->store('logos/clients', 'public');
        } else {
            unset($validated['logo']);
        }

        $client->update($validated);

        return back()->with('success', 'Client updated.');
    }
}
