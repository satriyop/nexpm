<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\MainContractor;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 15;
        $currentUser = $this->currentUser();

        return Inertia::render('admin/users/Index', [
            'users' => User::query()->whereScopedToMainContractor()->with('subcontractor', 'mainContractor')->latest('id')->paginate($perPage)->withQueryString(),
            'per_page' => $perPage,
            'subcontractors' => Subcontractor::query()->whereScopedToMainContractor()->with('mainContractors:id')->orderBy('name')->get(['id', 'name']),
            'mainContractors' => MainContractor::query()
                ->when(! $currentUser->isSuperAdmin(), fn ($query) => $query->whereKey($currentUser->main_contractor_id))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentUser = $this->currentUser();
        $mainContractorId = $currentUser->isSuperAdmin()
            ? $request->integer('main_contractor_id')
            : $currentUser->main_contractor_id;

        $subcontractorRule = $currentUser->isSuperAdmin()
            ? Rule::exists('subcontractors', 'id')
            : Rule::exists('main_contractor_subcontractor', 'subcontractor_id')
                ->where('main_contractor_id', $mainContractorId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', 'in:admin,subcontractor'],
            'main_contractor_id' => [
                'required_if:role,admin',
                'nullable',
                Rule::exists('main_contractors', 'id'),
            ],
            'subcontractor_id' => [
                'required_if:role,subcontractor',
                'nullable',
                $subcontractorRule,
            ],
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => Role::from($validated['role']),
            'main_contractor_id' => $validated['role'] === Role::Admin->value ? $mainContractorId : null,
            'subcontractor_id' => $validated['subcontractor_id'] ?? null,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = $this->currentUser();
        abort_unless($currentUser->isSuperAdmin(), 403);

        $mainContractorId = $request->integer('main_contractor_id') ?: null;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(8)],
            'role' => ['required', 'in:admin,subcontractor'],
            'main_contractor_id' => ['required_if:role,admin', 'nullable', Rule::exists('main_contractors', 'id')],
            'subcontractor_id' => ['required_if:role,subcontractor', 'nullable', Rule::exists('subcontractors', 'id')],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => Role::from($validated['role']),
            'main_contractor_id' => $validated['role'] === Role::Admin->value ? $mainContractorId : null,
            'subcontractor_id' => $validated['subcontractor_id'] ?? null,
            ...($validated['password'] ? ['password' => $validated['password']] : []),
        ]);

        return back()->with('success', 'User updated.');
    }
}
