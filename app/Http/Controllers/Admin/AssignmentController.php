<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SiteRowResource;
use App\Models\Assignment;
use App\Models\AssignmentAuditLog;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentConstructionPhoto;
use App\Models\AssignmentLegacyReport;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\MachineType;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Services\BastReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
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
                'machineType',
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

        if ($user->isGlobalAdmin() && $request->filled('main_contractor_id')) {
            $query->whereHas('project', fn ($q) => $q->where('main_contractor_id', $request->integer('main_contractor_id')));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('machine_type_id')) {
            $query->where('machine_type_id', $request->integer('machine_type_id'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 20;

        $sites = $query->latest('id')->paginate($perPage)->withQueryString();

        $sites->setCollection(
            $sites->getCollection()->map(fn ($site) => (new SiteRowResource($site))->resolve())
        );

        $mainContractorFilter = $user->isGlobalAdmin() && $request->filled('main_contractor_id')
            ? $request->integer('main_contractor_id')
            : null;

        $subcontractors = Subcontractor::query()
            ->when($mainContractorFilter, fn ($q) => $q->whereHas('mainContractors', fn ($query) => $query->whereKey($mainContractorFilter)))
            ->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereScopedToMainContractor())
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $projects = Project::query()
            ->when($mainContractorFilter, fn ($q) => $q->where('main_contractor_id', $mainContractorFilter))
            ->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereScopedToMainContractor())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/assignments/Index', [
            'sites' => $sites,
            'subcontractors' => $subcontractors,
            'projects' => $projects,
            'machineTypes' => MachineType::query()->orderBy('name')->get(['id', 'name']),
            'mainContractors' => $user->isGlobalAdmin()
                ? MainContractor::query()->orderBy('name')->get(['id', 'name'])
                : null,
            'per_page' => $perPage,
            'filters' => (object) $request->only(['search', 'status', 'activity_type', 'subcontractor_id', 'main_contractor_id', 'project_id', 'machine_type_id']),
        ]);
    }

    public function siteAssignments(Site $site): Response
    {
        $this->ensureCanAccessSite($site);

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
                ->whereHas('mainContractors', fn ($query) => $query->whereKey($site->project->main_contractor_id))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(Assignment $assignment): Response
    {
        $this->ensureCanAccessAssignment($assignment);

        $assignment->load([
            'site.siteType',
            'site.machineType',
            'site.project.mainContractor',
            'site.project.client',
            'subcontractor',
            'verifiedBy',
            'surveyData',
            'plnData',
            'constructionData.constructionPhotos',
            'bastData.bastPhotos',
            'legacyReports',
        ]);

        $mainContractorId = $assignment->site->project->main_contractor_id;

        $siblingConstruction = $assignment->activity_type === ActivityType::Bast
            ? Assignment::query()
                ->where('site_id', $assignment->site_id)
                ->where('activity_type', ActivityType::Construction)
                ->with('constructionData')
                ->first()?->constructionData
            : null;

        return Inertia::render('admin/assignments/Show', [
            'assignment' => $assignment,
            'siblingConstruction' => $siblingConstruction,
            'subcontractors' => Subcontractor::query()
                ->whereHas('mainContractors', fn ($query) => $query->whereKey($mainContractorId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'auditLogs' => Inertia::defer(fn () => AssignmentAuditLog::where('assignment_id', $assignment->id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'payload' => $log->payload,
                    'user' => $log->user?->only('id', 'name'),
                    'created_at' => $log->created_at?->toISOString(),
                ])
                ->all()),
        ]);
    }

    public function verify(Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

        abort_unless(
            in_array($assignment->status, AssignmentStatus::verifiableStatuses(), true),
            422,
            'Assignment must be in Document, Submitted, Live, or KWH Done state before verifying.',
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

    public function submitForReview(Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

        abort_unless($assignment->activity_type === ActivityType::Bast, 422, 'Only BAST assignments can be submitted.');
        abort_unless(
            in_array($assignment->status, [AssignmentStatus::Pending, AssignmentStatus::Revision], true),
            422,
            'Assignment is not in a submittable state.'
        );

        $assignment->markSubmitted();

        return back()->with('success', 'Submitted for review.');
    }

    public function unverify(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

        abort_unless(
            $assignment->status === AssignmentStatus::Verified,
            422,
            'Only verified assignments can be unverified.',
        );

        $validated = $request->validate([
            'unverify_reason' => ['required', 'string', 'min:1'],
        ]);

        $assignment->markUnverified($this->currentUser(), $validated['unverify_reason']);

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'unverified',
            'payload' => ['unverify_reason' => $validated['unverify_reason']],
        ]);

        return back()->with('success', 'Assignment unverified.');
    }

    public function revise(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

        abort_unless(
            $assignment->activity_type === ActivityType::Bast
            && $assignment->status === AssignmentStatus::Submitted,
            422,
            'Only BAST assignments in Submitted state can be sent for revision.',
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
        $this->ensureCanAccessAssignment($assignment);

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
        $this->ensureCanAccessAssignment($assignment);

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

    public function updateConstructionPrerequisite(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

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
        $this->ensureCanAccessAssignment($assignment);

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
            'photo_access_route' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_pln_network' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_satellite_gmaps' => ['nullable', 'file', 'image', 'max:10240'],
            'file_mockup_3d' => ['nullable', 'file', 'image', 'max:20480'],
            'file_site_plan' => ['nullable', 'file', 'image', 'max:20480'],
            'file_ba_survey' => ['nullable', 'file', 'image', 'max:10240'],
            'file_boq' => ['nullable', 'file', 'mimes:pdf,xlsx,xls', 'max:20480'],
            'ss_report_submission_date' => ['nullable', 'date'],
        ]);

        $validated['power_kva'] = $assignment->site?->power_kva;

        $fileFields = [
            'photo_overall_site', 'photo_parking_evcs', 'photo_access_route',
            'photo_pln_network', 'photo_satellite_gmaps',
            'file_mockup_3d', 'file_site_plan', 'file_ba_survey',
            'file_boq',
        ];

        $user = $this->currentUser();
        if (! $user->isAdmin() && ! $user->isSuperAdmin()) {
            unset($validated['file_boq']);
        }

        $survey = $assignment->surveyData()->firstOrNew([]);
        $survey->assignment_id = $assignment->id;
        $auditPayload = $validated;

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $path = $request->file($key)->store('survey', 'public');
                $survey->{$key} = $path;
                $auditPayload[$key] = $path;
            } elseif (! in_array($key, $fileFields)) {
                $survey->{$key} = $value;
            }
            // File fields with no upload are skipped — existing paths preserved
        }

        $survey->save();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'survey_edited',
            'payload' => $auditPayload,
        ]);

        return back()->with('success', 'Survey data updated.');
    }

    public function updatePlnData(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

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

        $plnFileFields = ['file_slo', 'file_nidi', 'file_reg', 'file_pk', 'foto_kwh'];

        $pln = $assignment->plnData()->firstOrNew([]);
        $pln->assignment_id = $assignment->id;
        $auditPayload = $validated;

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $path = $request->file($key)->store('pln', 'public');
                $pln->{$key} = $path;
                $auditPayload[$key] = $path;
            } elseif (! in_array($key, $plnFileFields)) {
                $pln->{$key} = $value;
            }
            // File fields with no upload are skipped — existing paths preserved
        }

        $pln->save();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'pln_edited',
            'payload' => $auditPayload,
        ]);

        return back()->with('success', 'PLN data updated.');
    }

    public function updateConstructionSubconData(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

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
        $auditPayload = $validated;

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $path = $request->file($key)->store('construction', 'public');
                $construction->{$key} = $path;
                $auditPayload[$key] = $path;
            } elseif ($key !== 'foto_machine_sn') {
                $construction->{$key} = $value;
            }
            // foto_machine_sn with no upload is skipped — existing path preserved
        }

        $construction->save();

        AssignmentAuditLog::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->currentUser()->id,
            'event' => 'construction_edited',
            'payload' => $auditPayload,
        ]);

        return back()->with('success', 'Construction data updated.');
    }

    public function storeConstructionPhoto(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);
        abort_unless($assignment->activity_type === ActivityType::Construction, 422, 'Activity type mismatch.');

        $request->validate([
            'photo' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $constructionData = $assignment->constructionData()->firstOrCreate([]);
        abort_if($constructionData->constructionPhotos()->count() >= 2, 422, 'Maximum of 2 progress photos allowed.');

        $path = $request->file('photo')->store('construction', 'public');

        AssignmentConstructionPhoto::query()->create([
            'assignment_construction_data_id' => $constructionData->id,
            'path' => $path,
        ]);

        $constructionData->touch();

        return back()->with('success', 'Photo uploaded.');
    }

    public function destroyConstructionPhoto(Assignment $assignment, AssignmentConstructionPhoto $photo): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);
        abort_unless($assignment->activity_type === ActivityType::Construction, 422, 'Activity type mismatch.');

        $constructionData = $photo->constructionData;
        abort_unless($constructionData->assignment_id === $assignment->id, 403);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        $constructionData->touch();

        return back()->with('success', 'Photo removed.');
    }

    public function storeBastPhoto(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);
        abort_unless($assignment->activity_type === ActivityType::Bast, 422, 'Activity type mismatch.');

        $request->validate([
            'section' => ['required', 'string'],
            'checkpoint_key' => ['required', 'string'],
            'photo' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $bastData = $assignment->bastData()->firstOrCreate([]);

        $existing = AssignmentBastPhoto::query()
            ->where('assignment_bast_data_id', $bastData->id)
            ->where('section', $request->string('section'))
            ->where('checkpoint_key', $request->string('checkpoint_key'))
            ->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->photo_path);
            $existing->delete();
        }

        $path = $request->file('photo')->store('bast', 'public');

        AssignmentBastPhoto::query()->create([
            'assignment_bast_data_id' => $bastData->id,
            'section' => $request->string('section'),
            'checkpoint_key' => $request->string('checkpoint_key'),
            'photo_path' => $path,
        ]);

        $bastData->touch();

        return back()->with('success', 'Photo uploaded.');
    }

    public function destroyBastPhoto(Assignment $assignment, AssignmentBastPhoto $photo): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);
        abort_unless($assignment->activity_type === ActivityType::Bast, 422, 'Activity type mismatch.');

        $bastData = $photo->bastData;
        abort_unless($bastData->assignment_id === $assignment->id, 403);

        Storage::disk('public')->delete($photo->photo_path);
        $photo->delete();

        $bastData->touch();

        return back()->with('success', 'Photo removed.');
    }

    public function updateBastData(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);

        $validated = $request->validate([
            'sim_provider' => ['nullable', 'string', 'max:255'],
            'nomor_simcard' => ['nullable', 'string', 'max:255'],
            'commissioning_date' => ['nullable', 'date'],
            'measurements' => ['nullable', 'array'],
            'measurements.*' => ['nullable', 'string', 'max:255'],
        ]);

        $bast = $assignment->bastData()->firstOrNew([]);
        $bast->assignment_id = $assignment->id;
        $bast->fill($validated);
        $bast->save();

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
        $this->ensureCanAccessAssignment($assignment);

        $user = $this->currentUser();
        $mainContractorId = $user->isGlobalAdmin()
            ? $assignment->load('site.project')->site->project->main_contractor_id
            : $user->main_contractor_id;

        $validated = $request->validate([
            'subcontractor_id' => [
                'required',
                Rule::exists('main_contractor_subcontractor', 'subcontractor_id')
                    ->where('main_contractor_id', $mainContractorId),
            ],
        ]);

        $newSubcon = Subcontractor::query()->findOrFail($validated['subcontractor_id']);

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
        $this->ensureCanAccessAssignment($assignment);

        abort_unless($assignment->activity_type === ActivityType::Bast, 422, 'Only BAST assignments have a commissioning report.');
        abort_unless(
            in_array($assignment->status, [AssignmentStatus::Submitted, AssignmentStatus::Verified, AssignmentStatus::Reported], true),
            422,
            'Report is only available once the assignment is submitted.'
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
        $this->ensureCanAccessSite($site);

        $user = $this->currentUser();
        $mainContractorId = $user->isGlobalAdmin()
            ? $site->load('project')->project->main_contractor_id
            : $user->main_contractor_id;

        $validated = $request->validate([
            'activity_type' => ['required', Rule::enum(ActivityType::class)],
            'subcontractor_id' => [
                'required',
                Rule::exists('main_contractor_subcontractor', 'subcontractor_id')
                    ->where('main_contractor_id', $mainContractorId),
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

        $subcon = Subcontractor::query()->findOrFail($validated['subcontractor_id']);
        $activityType = ActivityType::from($validated['activity_type']);

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
        $this->ensureCanAccessAssignment($assignment);

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

    public function bulkDrop(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:assignments,id'],
        ]);

        $assignments = Assignment::whereIn('id', $validated['assignment_ids'])
            ->whereHas('site.project', fn ($q) => $q->whereScopedToMainContractor())
            ->get();

        foreach ($assignments as $assignment) {
            $assignment->markDropped();
        }

        $count = $assignments->count();

        return back()->with('success', "{$count} assignment(s) archived.");
    }

    public function export(Request $request): HttpResponse
    {
        $siteIds = array_filter(explode(',', $request->string('site_ids', '')));

        $query = Site::query()
            ->with(['project', 'assignments.subcontractor'])
            ->whereHas('project', fn ($q) => $q->whereScopedToMainContractor());

        if (! empty($siteIds)) {
            $query->whereIn('id', $siteIds);
        }

        $sites = $query->orderBy('site_code')->get();

        $activityTypes = ['SURVEY', 'CONSTRUCTION', 'PLN_CONNECTION', 'BAST'];
        $sep = ';';

        $handle = fopen('php://memory', 'w');

        fputcsv($handle, [
            'Site Code', 'Location', 'City', 'Province', 'Project',
            'Survey Status', 'Survey Subcon',
            'Construction Status', 'Construction Subcon',
            'PLN Status', 'PLN Subcon',
            'BAST Status', 'BAST Subcon',
        ], $sep);

        foreach ($sites as $site) {
            $row = [
                $site->site_code,
                $site->location_name,
                $site->city ?? '',
                $site->province ?? '',
                $site->project?->name ?? '',
            ];

            foreach ($activityTypes as $type) {
                $assignment = $site->assignments->firstWhere('activity_type', $type);
                $row[] = $assignment?->status ?? '';
                $row[] = $assignment?->subcontractor?->name ?? '';
            }

            fputcsv($handle, $row, $sep);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'assignments-export-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function storeLegacyReport(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);
        abort_unless($this->currentUser()->isSuperAdmin(), 403, 'Super admin access required.');

        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:SSR,BAST,CONSTRUCTION'],
            'file' => ['required', 'file', 'mimes:pdf,xlsx,xls,jpg,jpeg,png', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store('legacy-reports', 'public');

        AssignmentLegacyReport::query()->create([
            'assignment_id' => $assignment->id,
            'report_type' => $validated['report_type'],
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by' => $this->currentUser()->id,
        ]);

        return back()->with('success', 'Legacy report uploaded.');
    }

    public function destroyLegacyReport(Assignment $assignment, AssignmentLegacyReport $legacyReport): RedirectResponse
    {
        $this->ensureCanAccessAssignment($assignment);
        abort_unless($this->currentUser()->isSuperAdmin(), 403, 'Super admin access required.');
        abort_unless($legacyReport->assignment_id === $assignment->id, 403);

        Storage::disk('public')->delete($legacyReport->file_path);
        $legacyReport->delete();

        return back()->with('success', 'Legacy report removed.');
    }
}
