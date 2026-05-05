<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\MainContractor;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users/Index', [
            'users'           => User::query()->whereScopedToMainContractor()->with('subcontractor', 'mainContractor')->latest('id')->paginate(15),
            'subcontractors'  => Subcontractor::query()->whereScopedToMainContractor()->orderBy('name')->get(['id', 'name', 'main_contractor_id']),
            'mainContractors' => MainContractor::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'            => ['required', Password::min(8)],
            'role'                => ['required', 'in:admin,subcontractor'],
            'main_contractor_id'  => ['required_if:role,admin', 'nullable', 'exists:main_contractors,id'],
            'subcontractor_id'    => ['required_if:role,subcontractor', 'nullable', 'exists:subcontractors,id'],
        ]);

        User::query()->create([
            'name'                => $validated['name'],
            'email'               => $validated['email'],
            'password'            => $validated['password'],
            'role'                => Role::from($validated['role']),
            'main_contractor_id'  => $validated['main_contractor_id'] ?? null,
            'subcontractor_id'    => $validated['subcontractor_id'] ?? null,
            'email_verified_at'   => now(),
        ]);

        return back()->with('success', 'User created successfully.');
    }
}
