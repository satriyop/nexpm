<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingController extends Controller
{
    public function index(): Response
    {
        $logoPath = AppSetting::get('company.logo');

        return Inertia::render('admin/settings/Company', [
            'companyName' => AppSetting::get('company.name', ''),
            'logoUrl' => $logoPath ? Storage::url($logoPath) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        AppSetting::set('company.name', $request->input('company_name', ''));

        if ($request->hasFile('logo')) {
            $oldPath = AppSetting::get('company.logo');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('logo')->store('logos/company', 'public');
            AppSetting::set('company.logo', $path);
        }

        return back()->with('success', 'Company settings updated.');
    }
}
