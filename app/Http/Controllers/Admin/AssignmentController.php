<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SiteRowResource;
use App\Models\Assignment;
use App\Models\AssignmentAuditLog;
use App\Models\AssignmentBastData;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\BastReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Site::query()
            ->with([
                'siteType',
                'project',
                'assignments.subcontractor',
                'assignments.constructionData',
            ])
            ->whereHas('assignments')
            ->whereHas('project', fn ($q) => $q->whereScopedToMainContractor());

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('site_code', 'like', "%{$search}%")
                    ->orWhere('location_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%");
            });
        }

        $filteringByDrop = $request->input('status') === 'DROP';

        $query->whereHas('assignments', function ($q) use ($request, $filteringByDrop): void {
            if ($request->filled('status')) {
                $q->where('status', $request->string('status'));
            } elseif (! $filteringByDrop) {
                $q->where('status', '!=', 'DROP');
            }
            if ($request->filled('activity_type')) {
                $q->where('activity_type', $request->string('activity_type'));
            }
        });

        if ($request->filled('subcontractor_id')) {
            $query->whereHas('assignments', fn ($q) => $q->where('subcontractor_id', $request->integer('subcontractor_id')));
        }

        $user = $this->currentUser();

        if ($user->isSuperAdmin() && $request->filled('main_contractor_id')) {
            $query->whereHas('project', fn ($q) => $q->where('main_contractor_id', $request->integer('main_contractor_id')));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        $sites = $query->latest('id')->paginate(20)->withQueryString();

        $sites->setCollection(
            $sites->getCollection()->map(fn ($site) => (new SiteRowResource($site))->resolve())
        );

        $mainContractorFilter = $user->isSuperAdmin() && $request->filled('main_contractor_id')
            ? $request->integer('main_contractor_id')
            : null;

        $subcontractors = Subcontractor::query()
            ->when($mainContractorFilter, fn ($q) => $q->where('main_contractor_id', $mainContractorFilter))
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereScopedToMainContractor())
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $projects = Project::query()
            ->when($mainContractorFilter, fn ($q) => $q->where('main_contractor_id', $mainContractorFilter))
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereScopedToMainContractor())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/assignments/Index', [
            'sites' => $sites,
            'subcontractors' => $subcontractors,
            'projects' => $projects,
            'mainContractors' => $user->isSuperAdmin()
                ? MainContractor::query()->orderBy('name')->get(['id', 'name'])
                : null,
            'filters' => (object) $request->only(['search', 'status', 'activity_type', 'subcontractor_id', 'main_contractor_id', 'project_id']),
        ]);
    }

    public function siteAssignments(Site $site): Response
    {
        $site->load(['siteType', 'project.client', 'project.mainContractor']);

        $assignments = Assignment::query()
            ->where('site_id', $site->id)
            ->with([
                'subcontractor',
                'surveyData',
                'plnData',
                'constructionData',
                'bastData',
            ])
            ->get();

        return Inertia::render('admin/assignments/SiteShow', [
            'site' => $site,
            'assignments' => $assignments,
            'subcontractors' => Subcontractor::query()
                ->whereScopedToMainContractor()
                ->with('subcontractorType')
                ->orderBy('name')
                ->get(['id', 'name', 'subcontractor_type_id']),
        ]);
    }

    public function show(Assignment $assignment): Response
    {
        $assignment->load([
            'site.siteType',
            'subcontractor',
            'verifiedBy',
            'surveyData',
            'plnData',
            'constructionData.constructionPhotos',
            'bastData.bastPhotos',
        ]);

        return Inertia::render('admin/assignments/Show', [
            'assignment' => $assignment,
            'subcontractors' => Subcontractor::query()
                ->whereScopedToMainContractor()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function verify(Assignment $assignment): RedirectResponse
    {
        abort_unless(
            in_array($assignment->status, AssignmentStatus::verifiableStatuses(), true),
            422,
            'Assignment must be in Document or Completed state before verifying.',
        );

        $assignment->markVerified($this->currentUser());

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'verified',
            'payload' => null,
        ]);

        return back()->with('success', 'Assignment verified.');
    }

    public function revise(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless(
            $assignment->activity_type === ActivityType::Bast
            && $assignment->status === AssignmentStatus::Completed,
            422,
            'Only BAST assignments in Completed state can be sent for revision.',
        );

        $validated = $request->validate([
            'revision_comment' => ['required', 'string', 'min:1'],
        ]);

        $assignment->sendToRevision($validated['revision_comment']);

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'revised',
            'payload' => ['revision_comment' => $validated['revision_comment']],
        ]);

        return back()->with('success', 'Revision request sent.');
    }

    public function drop(Assignment $assignment): RedirectResponse
    {
        abort_unless(
            ! in_array($assignment->status, [AssignmentStatus::Drop, AssignmentStatus::Reported], true),
            422,
            'Assignment cannot be dropped in its current state.',
        );

        $assignment->markDropped();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'dropped',
            'payload' => null,
        ]);

        return back()->with('success', 'Assignment dropped.');
    }

    public function restore(Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->status === AssignmentStatus::Drop, 422, 'Only dropped assignments can be restored.');

        $assignment->status = AssignmentStatus::Pending;
        $assignment->save();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'restored',
            'payload' => null,
        ]);

        return back()->with('success', 'Assignment restored.');
    }

    public function updateSurveyParkingSlot(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->activity_type === ActivityType::Survey, 422, 'Only Survey assignments have a parking slot.');

        $validated = $request->validate([
            'parking_slot' => ['nullable', 'string', 'max:255'],
        ]);

        $survey = $assignment->surveyData()->firstOrCreate(['assignment_id' => $assignment->id]);
        $survey->parking_slot = $validated['parking_slot'];
        $survey->save();

        return back()->with('success', 'Parking slot saved.');
    }

    public function updateConstructionPrerequisite(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->activity_type === ActivityType::Construction, 422, 'Only Construction assignments accept this prerequisite.');

        $validated = $request->validate([
            'cons_wo_number' => ['required', 'string', 'max:255'],
            'project_status' => ['nullable', 'string', 'max:255'],
            'setup_approval_date' => ['nullable', 'date'],
        ]);

        $constructionData = $assignment->constructionData()->firstOrCreate([]);
        $constructionData->fill($validated)->save();

        return back()->with('success', 'Construction prerequisite saved.');
    }

    public function updateSurveyData(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'surveyor_name' => ['nullable', 'string', 'max:255'],
            'pic_location_name' => ['nullable', 'string', 'max:255'],
            'pic_location_phone' => ['nullable', 'string', 'max:255'],
            'charger_type' => ['nullable', 'string', 'max:255'],
            'ss_schedule_date' => ['nullable', 'date'],
            'cable_pulling_type' => ['nullable', 'string', 'max:255'],
            'power_kva' => ['nullable', 'string', 'max:255'],
            'pln_network_type' => ['nullable', 'string', 'max:255'],
            'parking_slot' => ['nullable', 'string', 'max:255'],
            'additional_info' => ['nullable', 'string'],
            'photo_overall_site' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_parking_evcs' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_other_angle' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_pln_network' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_satellite_gmaps' => ['nullable', 'file', 'image', 'max:10240'],
            'file_mockup_3d' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,dwg', 'max:20480'],
            'file_ba_survey' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $survey = $assignment->surveyData()->firstOrNew([]);
        $survey->assignment_id = $assignment->id;

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $survey->{$key} = $request->file($key)->store('survey', 'public');
            } else {
                $survey->{$key} = $value;
            }
        }

        $survey->saveQuietly();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'survey_edited',
            'payload' => $validated,
        ]);

        return back()->with('success', 'Survey data updated.');
    }

    public function updatePlnData(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'pln_status' => ['nullable', 'string', 'max:255'],
            'nidi_slo_date_acquired' => ['nullable', 'date'],
            'type_rate' => ['nullable', 'string', 'max:255'],
            'kwh_meter_installation_date' => ['nullable', 'date'],
            'id_pelanggan' => ['nullable', 'string', 'max:255'],
            'catatan_progres' => ['nullable', 'string'],
            'file_slo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'file_nidi' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'file_reg' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'file_pk' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'email_bpujl_req_date' => ['nullable', 'date'],
            'bpujl_acquired_date' => ['nullable', 'date'],
            'foto_kwh' => ['nullable', 'file', 'image', 'max:10240'],
        ]);

        $pln = $assignment->plnData()->firstOrNew([]);
        $pln->assignment_id = $assignment->id;

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $pln->{$key} = $request->file($key)->store('pln', 'public');
            } else {
                $pln->{$key} = $value;
            }
        }

        $pln->saveQuietly();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'pln_edited',
            'payload' => $validated,
        ]);

        return back()->with('success', 'PLN data updated.');
    }

    public function updateConstructionSubconData(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'cons_actual_start_date' => ['nullable', 'date'],
            'cons_actual_done_date' => ['nullable', 'date'],
            'machine_serial_number' => ['nullable', 'string', 'max:255'],
            'foto_machine_sn' => ['nullable', 'file', 'image', 'max:10240'],
            'catatan_progres' => ['nullable', 'string'],
            'go_live_date_pln' => ['nullable', 'date'],
            'go_live_date_pln_pass' => ['nullable', 'date'],
        ]);

        $construction = $assignment->constructionData()->firstOrCreate(['assignment_id' => $assignment->id]);

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $construction->{$key} = $request->file($key)->store('construction', 'public');
            } else {
                $construction->{$key} = $value;
            }
        }

        $construction->saveQuietly();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'construction_edited',
            'payload' => $validated,
        ]);

        return back()->with('success', 'Construction data updated.');
    }

    public function updateBastData(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'plant_name' => ['nullable', 'string', 'max:255'],
            'plant_address' => ['nullable', 'string'],
            'plant_coordinate' => ['nullable', 'string', 'max:255'],
            'gmaps_link' => ['nullable', 'string', 'max:500'],
            'charger_type' => ['nullable', 'string', 'max:255'],
            'sn_unit' => ['nullable', 'string', 'max:255'],
            'id_pln' => ['nullable', 'string', 'max:255'],
            'sim_provider' => ['nullable', 'string', 'max:255'],
            'installation_vendor' => ['nullable', 'string', 'max:255'],
            'pic_vendor_contact' => ['nullable', 'string', 'max:255'],
            'installation_date' => ['nullable', 'date'],
            'commissioning_date' => ['nullable', 'date'],
            'customer' => ['nullable', 'string', 'max:255'],
            'nomor_simcard' => ['nullable', 'string', 'max:255'],
            'go_live_date_pln_pass' => ['nullable', 'date'],
            'go_live_date_pln' => ['nullable', 'date'],
            'measurements' => ['nullable', 'array'],
            'measurements.*' => ['nullable', 'string', 'max:255'],
        ]);

        $bast = $assignment->bastData()->firstOrNew([]);
        $bast->assignment_id = $assignment->id;
        $bast->fill($validated);
        $bast->saveQuietly();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'bast_edited',
            'payload' => $validated,
        ]);

        return back()->with('success', 'BAST data updated.');
    }

    public function reassign(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'subcontractor_id' => [
                'required',
                Rule::exists('subcontractors', 'id')
                    ->where('main_contractor_id', $this->currentUser()->main_contractor_id),
            ],
        ]);

        $newSubcon = Subcontractor::query()->with('subcontractorType')->findOrFail($validated['subcontractor_id']);

        abort_unless(
            $newSubcon->subcontractorType === null || $newSubcon->subcontractorType->handlesActivity($assignment->activity_type),
            422,
            "Subcontractor type '{$newSubcon->subcontractorType?->name}' cannot handle {$assignment->activity_type->label()} assignments."
        );

        // Delete all activity data (cascade handles photos)
        $assignment->surveyData?->delete();
        $assignment->plnData?->delete();
        $assignment->constructionData?->delete();
        $assignment->bastData?->delete();

        $assignment->subcontractor_id = $validated['subcontractor_id'];
        $assignment->status = AssignmentStatus::Pending;
        $assignment->revision_comment = null;
        $assignment->verified_at = null;
        $assignment->verified_by = null;
        $assignment->reported_at = null;
        $assignment->saveQuietly();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'reassigned',
            'payload' => ['new_subcontractor_id' => $validated['subcontractor_id']],
        ]);

        return back()->with('success', 'Assignment reassigned and reset to pending.');
    }

    public function downloadBastReport(Assignment $assignment, BastReportExportService $exportService): HttpResponse
    {
        abort_unless($assignment->activity_type === ActivityType::Bast, 422, 'Only BAST assignments have a commissioning report.');
        abort_unless(
            in_array($assignment->status, [AssignmentStatus::Completed, AssignmentStatus::Verified, AssignmentStatus::Reported], true),
            422,
            'Report is only available once the assignment is completed.'
        );

        $spreadsheet = $exportService->generate($assignment);

        $filename = sprintf(
            'COMM-TEST-%s-%s.xlsx',
            $assignment->site->site_code,
            now()->format('Ymd')
        );

        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function storeForSite(Request $request, Site $site): RedirectResponse
    {
        $validated = $request->validate([
            'activity_type' => ['required', Rule::enum(ActivityType::class)],
            'subcontractor_id' => [
                'required',
                Rule::exists('subcontractors', 'id')
                    ->where('main_contractor_id', $this->currentUser()->main_contractor_id),
            ],
        ]);

        abort_if(
            Assignment::query()
                ->where('site_id', $site->id)
                ->where('activity_type', $validated['activity_type'])
                ->exists(),
            422,
            'An assignment of this type already exists for this site.'
        );

        $subcon = Subcontractor::query()->with('subcontractorType')->findOrFail($validated['subcontractor_id']);
        $activityType = ActivityType::from($validated['activity_type']);

        abort_unless(
            $subcon->subcontractorType === null || $subcon->subcontractorType->handlesActivity($activityType),
            422,
            "Subcontractor type '{$subcon->subcontractorType?->name}' cannot handle {$activityType->label()} assignments."
        );

        $assignment = Assignment::query()->create([
            'site_id' => $site->id,
            'subcontractor_id' => $subcon->id,
            'activity_type' => $activityType,
            'status' => AssignmentStatus::Pending,
        ]);

        match ($activityType) {
            ActivityType::Survey => AssignmentSurveyData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
            ActivityType::PlnConnection => AssignmentPlnData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
            ActivityType::Construction => AssignmentConstructionData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
            ActivityType::Bast => AssignmentBastData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
        };

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'created',
            'payload' => ['subcontractor_id' => $subcon->id],
        ]);

        return back()->with('success', 'Assignment created.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        abort_unless(
            $assignment->status === AssignmentStatus::Pending,
            422,
            'Only pending assignments can be removed.'
        );

        $assignment->surveyData?->delete();
        $assignment->plnData?->delete();
        $assignment->constructionData?->delete();
        $assignment->bastData?->delete();
        $assignment->delete();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'deleted',
            'payload' => null,
        ]);

        return back()->with('success', 'Assignment removed.');
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
