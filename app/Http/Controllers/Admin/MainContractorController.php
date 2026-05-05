<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MainContractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MainContractorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/main-contractors/Index', [
            'mainContractors' => MainContractor::query()->latest('id')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'pic'   => ['nullable', 'string', 'max:255'],
        ]);

        MainContractor::query()->create($validated);

        return back()->with('success', 'Main contractor created.');
    }
}
