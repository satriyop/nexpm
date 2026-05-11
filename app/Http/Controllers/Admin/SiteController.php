<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\MachineType;
use App\Models\Site;
use App\Models\SiteType;
use App\Models\Subcontractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function edit(Site $site): Response
    {
        $this->ensureCanAccessSite($site);

        $site->load(['siteType', 'machineType', 'project.client', 'project.mainContractor']);

        $assignments = Assignment::query()
            ->where('site_id', $site->id)
            ->with(['subcontractor', 'surveyData', 'plnData', 'constructionData', 'bastData'])
            ->get();

        return Inertia::render('admin/sites/Edit', [
            'site' => $site,
            'siteTypes' => SiteType::orderBy('name')->get(['id', 'name']),
            'machineTypes' => MachineType::orderBy('name')->get(['id', 'name']),
            'assignments' => $assignments,
            'subcontractors' => Subcontractor::query()
                ->whereHas('mainContractors', fn ($query) => $query->whereKey($site->project->main_contractor_id))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->ensureCanAccessSite($site);

        $validated = $request->validate([
            'location_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'google_map_url' => ['nullable', 'string', 'max:500'],
            'bd_pic' => ['nullable', 'string', 'max:255'],
            'site_type_id' => ['nullable', 'exists:site_types,id'],
            'machine_type_id' => ['nullable', 'exists:machine_types,id'],
            'ss_wo_number' => ['nullable', 'string', 'max:255'],
            'cable_length_to_panel' => ['nullable', 'numeric', 'min:0'],
            'cable_length_panel_to_charger' => ['nullable', 'numeric', 'min:0'],
            'charging_station_count' => ['nullable', 'integer', 'min:0'],
            'power_kva' => ['nullable', 'string', 'max:255'],
            'ssr_url' => ['nullable', 'string', 'max:500'],
            'nidi_slo_bpujl_url' => ['nullable', 'string', 'max:500'],
            'sik_url' => ['nullable', 'string', 'max:500'],
            'latest_remark' => ['nullable', 'string'],
            'invoice_submission_date' => ['nullable', 'date'],
            'dp_35_date' => ['nullable', 'date'],
            'invoice_60_submission_date' => ['nullable', 'date'],
            'payment_60_date' => ['nullable', 'date'],
            'invoice_5_submission_date' => ['nullable', 'date'],
            'payment_5_date' => ['nullable', 'date'],
            'invoice_url' => ['nullable', 'string', 'max:500'],
        ]);

        $site->update($validated);

        return redirect()->route('admin.sites.edit', $site)->with('success', 'Site updated.');
    }
}
