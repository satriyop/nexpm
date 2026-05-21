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
            'clients' => Client::query()
                ->whereScopedToMainContractor()
                ->with('mainContractors')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString(),
            'per_page' => $perPage,
            'mainContractors' => MainContractor::query()
                ->when(! $this->currentUser()->isGlobalAdmin(), fn ($query) => $query->whereKey($this->currentUser()->main_contractor_id))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->currentUser();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'main_contractor_ids' => ['required', 'array', 'min:1'],
            'main_contractor_ids.*' => ['required', 'integer', Rule::exists('main_contractors', 'id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
        ]);

        $client = Client::query()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'pic' => $validated['pic'] ?? null,
        ]);

        $client->mainContractors()->attach($validated['main_contractor_ids']);

        return back()->with('success', 'Client created.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->ensureCanAccessClient($client);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'main_contractor_ids' => ['required', 'array', 'min:1'],
            'main_contractor_ids.*' => ['required', 'integer', Rule::exists('main_contractors', 'id')],
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

        $client->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'pic' => $validated['pic'] ?? null,
            'logo' => $validated['logo'] ?? $client->logo,
        ]);

        $client->mainContractors()->sync($validated['main_contractor_ids']);

        return back()->with('success', 'Client updated.');
    }

    private function ensureCanAccessClient(Client $client): void
    {
        $user = $this->currentUser();

        if ($user->isGlobalAdmin()) {
            return;
        }

        $hasAccess = $client->mainContractors()
            ->where('main_contractors.id', $user->main_contractor_id)
            ->exists();

        abort_unless($hasAccess, 403, 'You do not have access to this client.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->ensureCanAccessClient($client);

        // Prevent deletion if related data exists
        if ($client->projects()->exists()) {
            return back()->with('error', 'Cannot delete client. It has associated projects.');
        }

        // Delete logo if exists
        if ($client->logo) {
            Storage::disk('public')->delete($client->logo);
        }

        // Detach from main contractors and delete
        $client->mainContractors()->detach();
        $client->delete();

        return back()->with('success', 'Client deleted.');
    }
}
