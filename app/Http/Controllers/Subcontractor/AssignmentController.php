<?php

namespace App\Http\Controllers\Subcontractor;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
use App\Models\AssignmentConstructionPhoto;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->currentUser();

        $query = Assignment::query()
            ->where('subcontractor_id', $user->subcontractor_id)
            ->with(['site', 'surveyData', 'plnData', 'constructionData', 'bastData']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('site', fn ($q) => $q
                ->where('site_code', 'like', "%{$search}%")
                ->orWhere('location_name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('province', 'like', "%{$search}%")
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $assignments = $query->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('subcontractor/assignments/Index', [
            'assignments' => $assignments,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Assignment $assignment): Response
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);

        $assignment->load([
            'site.siteType',
            'surveyData',
            'plnData',
            'constructionData.constructionPhotos',
            'bastData.bastPhotos',
        ]);

        return Inertia::render('subcontractor/assignments/Show', [
            'assignment' => $assignment,
            'isLocked' => $assignment->isLocked(),
        ]);
    }

    public function updateSurveyData(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);
        abort_unless($assignment->activity_type === ActivityType::Survey, 422, 'Activity type mismatch.');

        $validated = $request->validate([
            'surveyor_name' => ['nullable', 'string', 'max:255'],
            'pic_location_name' => ['nullable', 'string', 'max:255'],
            'pic_location_phone' => ['nullable', 'string', 'max:255'],
            'charger_type' => ['nullable', 'string', 'max:255'],
            'ss_schedule_date' => ['nullable', 'date'],
            'cable_pulling_type' => ['nullable', 'string', 'max:255'],
            'power_kva' => ['nullable', 'string', 'max:255'],
            'pln_network_type' => ['nullable', 'string', 'max:255'],
            'additional_info' => ['nullable', 'string'],
            'photo_overall_site' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_parking_evcs' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_other_angle' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_pln_network' => ['nullable', 'file', 'image', 'max:10240'],
            'photo_satellite_gmaps' => ['nullable', 'file', 'image', 'max:10240'],
            'file_mockup_3d' => ['nullable', 'file', 'max:20480'],
            'file_ba_survey' => ['nullable', 'file', 'max:20480'],
            'parking_slot' => ['nullable', 'string', 'max:255'],
        ]);

        $survey = AssignmentSurveyData::query()->firstOrNew(['assignment_id' => $assignment->id]);

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $survey->{$key} = $request->file($key)->store('survey', 'public');
            } else {
                $survey->{$key} = $value;
            }
        }

        $survey->assignment_id = $assignment->id;
        $survey->save();

        return back()->with('success', 'Survey data saved.');
    }

    public function updatePlnData(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);
        abort_unless($assignment->activity_type === ActivityType::PlnConnection, 422, 'Activity type mismatch.');

        $validated = $request->validate([
            'pln_status' => ['nullable', 'string', 'max:255'],
            'nidi_slo_date_acquired' => ['nullable', 'date'],
            'type_rate' => ['nullable', 'string', 'max:255'],
            'file_slo' => ['nullable', 'file', 'max:20480'],
            'file_nidi' => ['nullable', 'file', 'max:20480'],
            'file_reg' => ['nullable', 'file', 'max:20480'],
            'kwh_meter_installation_date' => ['nullable', 'date'],
            'id_pelanggan' => ['nullable', 'string', 'max:255'],
            'catatan_progres' => ['nullable', 'string'],
        ]);

        $pln = AssignmentPlnData::query()->firstOrNew(['assignment_id' => $assignment->id]);

        foreach ($validated as $key => $value) {
            if ($request->hasFile($key)) {
                $pln->{$key} = $request->file($key)->store('pln', 'public');
            } else {
                $pln->{$key} = $value;
            }
        }

        $pln->assignment_id = $assignment->id;
        $pln->save();

        return back()->with('success', 'PLN data saved.');
    }

    public function updateConstructionData(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);
        abort_unless($assignment->activity_type === ActivityType::Construction, 422, 'Activity type mismatch.');

        $constructionData = $assignment->constructionData()->firstOrCreate([]);
        abort_unless($constructionData->isPrerequisiteMet(), 423, 'Awaiting WO Number from admin.');

        $validated = $request->validate([
            'cons_actual_start_date' => ['nullable', 'date'],
            'cons_actual_done_date' => ['nullable', 'date'],
            'machine_serial_number' => ['nullable', 'string', 'max:255'],
            'catatan_progres' => ['nullable', 'string'],
        ]);

        $constructionData->fill($validated)->save();

        return back()->with('success', 'Construction data saved.');
    }

    public function storeConstructionPhoto(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);
        abort_unless($assignment->activity_type === ActivityType::Construction, 422, 'Activity type mismatch.');

        $request->validate([
            'photo' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $constructionData = $assignment->constructionData()->firstOrCreate([]);
        abort_unless($constructionData->isPrerequisiteMet(), 423, 'Awaiting WO Number from admin.');

        $path = $request->file('photo')->store('construction', 'public');

        AssignmentConstructionPhoto::query()->create([
            'assignment_construction_data_id' => $constructionData->id,
            'path' => $path,
        ]);

        // Trigger isComplete recalculation by touching the parent.
        $constructionData->touch();

        return back()->with('success', 'Photo uploaded.');
    }

    public function updateBastData(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);
        abort_unless($assignment->activity_type === ActivityType::Bast, 422, 'Activity type mismatch.');

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
            'measurements' => ['nullable', 'array'],
            'measurements.*' => ['nullable', 'string', 'max:255'],
            'nomor_simcard' => ['nullable', 'string', 'max:255'],
            'go_live_date_pln_pass' => ['nullable', 'date'],
            'go_live_date_pln' => ['nullable', 'date'],
        ]);

        $bast = AssignmentBastData::query()->firstOrNew(['assignment_id' => $assignment->id]);
        $bast->fill($validated);
        $bast->assignment_id = $assignment->id;
        $bast->save();

        return back()->with('success', 'BAST data saved.');
    }

    public function storeBastPhoto(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);
        abort_unless($assignment->activity_type === ActivityType::Bast, 422, 'Activity type mismatch.');

        $request->validate([
            'section' => ['required', 'string', 'max:100'],
            'checkpoint_key' => ['required', 'string', 'max:100'],
            'photo' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $bast = AssignmentBastData::query()->firstOrCreate(
            ['assignment_id' => $assignment->id]
        );

        $path = $request->file('photo')->store('bast', 'public');

        // Replace existing photo for this checkpoint if it exists.
        $existing = $bast->bastPhotos()
            ->where('checkpoint_key', $request->string('checkpoint_key'))
            ->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->photo_path);
            $existing->update(['photo_path' => $path]);
        } else {
            AssignmentBastPhoto::query()->create([
                'assignment_bast_data_id' => $bast->id,
                'section' => $request->string('section'),
                'checkpoint_key' => $request->string('checkpoint_key'),
                'photo_path' => $path,
            ]);
        }

        $bast->touch();

        return back()->with('success', 'Photo uploaded.');
    }

    public function destroyConstructionPhoto(Assignment $assignment, AssignmentConstructionPhoto $photo): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);
        abort_unless($assignment->activity_type === ActivityType::Construction, 422, 'Activity type mismatch.');

        $constructionData = $photo->constructionData;
        abort_unless($constructionData->assignment_id === $assignment->id, 403);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        $constructionData->touch();

        return back()->with('success', 'Photo removed.');
    }

    public function destroyBastPhoto(Assignment $assignment, AssignmentBastPhoto $photo): RedirectResponse
    {
        $this->ensureBelongsToCurrentSubcontractor($assignment);
        $this->ensureEditable($assignment);

        $bast = $photo->bastData;
        abort_unless($bast->assignment_id === $assignment->id, 403);

        Storage::disk('public')->delete($photo->photo_path);
        $photo->delete();

        $bast->touch();

        return back()->with('success', 'Photo removed.');
    }

    private function ensureBelongsToCurrentSubcontractor(Assignment $assignment): void
    {
        $user = $this->currentUser();

        abort_unless(
            $user->subcontractor_id !== null && $assignment->subcontractor_id === $user->subcontractor_id,
            403,
            'Assignment does not belong to your sub-contractor.'
        );
    }

    private function ensureEditable(Assignment $assignment): void
    {
        abort_if(
            in_array($assignment->status, [AssignmentStatus::Verified, AssignmentStatus::Reported], true),
            422,
            'Assignment is locked because it has been verified or reported.'
        );
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
