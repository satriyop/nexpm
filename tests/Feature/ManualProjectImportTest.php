<?php

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Imports\ManualProjectImport;
use App\Models\Assignment;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentLegacyReport;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\Client;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Build a semicolon-delimited CSV string from rows of data.
 *
 * @param  array<int, array<string, string>>  $rows
 */
function buildCsv(array $rows): string
{
    $sep = ';';
    $handle = fopen('php://memory', 'w');
    fputcsv($handle, ManualProjectImport::COLUMNS, $sep);
    foreach ($rows as $row) {
        $line = array_map(fn ($col) => $row[$col] ?? '', ManualProjectImport::COLUMNS);
        fputcsv($handle, $line, $sep);
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

/**
 * Make an UploadedFile from a CSV string for use in HTTP requests.
 */
function csvFile(string $csv): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($path, $csv);

    return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
}

// ── Authorization ─────────────────────────────────────────────────────────────

test('non-superadmin cannot access the import page', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.manual-import.index'))
        ->assertForbidden();
});

test('superadmin can access the import index page', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($superAdmin)
        ->get(route('admin.manual-import.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('admin/manual-import/Index'));
});

test('superadmin can download the CSV template', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($superAdmin)
        ->get(route('admin.manual-import.template'))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
});

// ── Preview ───────────────────────────────────────────────────────────────────

test('preview parses valid CSV and returns rows without writing to DB', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek Test',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-001',
        'location_name' => 'Lokasi Test',
        'activity_type' => 'SURVEY',
        'subcontractor_code' => 'SUB-001',
        'status' => 'REPORTED',
    ]]);

    $sitesBefore = Site::count();

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.preview'), ['file' => csvFile($csv)])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/manual-import/Index')
            ->has('previewRows', 1)
            ->where('previewSummary.ok', 1)
            ->where('previewSummary.errors', 0)
        );

    // Preview must not write anything to the DB.
    expect(Site::count())->toBe($sitesBefore)
        ->and(Project::count())->toBe(0)
        ->and(Assignment::count())->toBe(0);
});

test('preview flags unknown subcontractor code as error', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    MainContractor::factory()->create(['name' => 'PT Kontraktor A']);

    $csv = buildCsv([[
        'project_name' => 'Proyek Test',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-001',
        'location_name' => 'Lokasi',
        'activity_type' => 'SURVEY',
        'subcontractor_code' => 'UNKNOWN-SUB',
    ]]);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.preview'), ['file' => csvFile($csv)])
        ->assertInertia(fn ($page) => $page
            ->where('previewSummary.errors', 1)
            ->where('previewSummary.ok', 0)
        );
});

test('preview flags invalid activity_type as error', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $csv = buildCsv([[
        'project_name' => 'Test',
        'site_code' => 'SITE-001',
        'location_name' => 'Lokasi',
        'activity_type' => 'INVALID_TYPE',
    ]]);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.preview'), ['file' => csvFile($csv)])
        ->assertInertia(fn ($page) => $page
            ->where('previewSummary.errors', 1)
        );
});

// ── Store (commit) ────────────────────────────────────────────────────────────

test('store creates a new project, site, and assignment at reported status', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    $client = Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek Baru',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-001',
        'location_name' => 'Lokasi A',
        'address' => 'Jl. Merdeka 1',
        'province' => 'Jawa Barat',
        'city' => 'Bandung',
        'google_map_url' => 'https://maps.google.com/?q=-6.917464,107.619123',
        'activity_type' => 'SURVEY',
        'subcontractor_code' => 'SUB-001',
        'status' => 'REPORTED',
    ]]);

    $tempPath = 'temp/manual-import/test.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath])
        ->assertRedirect(route('admin.projects.index'));

    $project = Project::where('name', 'Proyek Baru')->first();
    expect($project)->not->toBeNull()
        ->and($project->client_id)->toBe($client->id)
        ->and($project->main_contractor_id)->toBe($mc->id);

    $site = Site::where('site_code', 'SITE-001')->first();
    expect($site)->not->toBeNull()
        ->and($site->latitude)->toBe('-6.9174640')
        ->and($site->longitude)->toBe('107.6191230');

    $assignment = Assignment::where('activity_type', ActivityType::Survey)->first();
    expect($assignment)->not->toBeNull()
        ->and($assignment->status)->toBe(AssignmentStatus::Reported)
        ->and($assignment->subcontractor_id)->toBe($sub->id);
});

test('store populates survey activity data from CSV columns', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek Survey',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-S01',
        'location_name' => 'Lokasi Survey',
        'activity_type' => 'SURVEY',
        'subcontractor_code' => 'SUB-001',
        'status' => 'REPORTED',
        'ss_schedule_date' => '2025-01-10',
        'parking_slot' => '5',
        'cable_pulling_type' => 'Underground',
    ]]);

    $tempPath = 'temp/manual-import/survey.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath]);

    $survey = AssignmentSurveyData::first();
    expect($survey)->not->toBeNull()
        ->and($survey->parking_slot)->toBe('5')
        ->and($survey->cable_pulling_type)->toBe('Underground')
        ->and($survey->ss_schedule_date->format('Y-m-d'))->toBe('2025-01-10');
});

test('store populates construction activity data from CSV columns', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek Cons',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-C01',
        'location_name' => 'Lokasi Cons',
        'activity_type' => 'CONSTRUCTION',
        'subcontractor_code' => 'SUB-001',
        'status' => 'REPORTED',
        'cons_wo_number' => 'WO-2025-001',
        'cons_actual_start_date' => '2025-02-01',
        'cons_actual_done_date' => '2025-03-01',
        'machine_serial_number' => 'SN-12345',
        'go_live_date_pln' => '2025-03-10',
    ]]);

    $tempPath = 'temp/manual-import/construction.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath]);

    $data = AssignmentConstructionData::first();
    expect($data)->not->toBeNull()
        ->and($data->cons_wo_number)->toBe('WO-2025-001')
        ->and($data->machine_serial_number)->toBe('SN-12345')
        ->and($data->go_live_date_pln->format('Y-m-d'))->toBe('2025-03-10');
});

test('store populates PLN activity data from CSV columns', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek PLN',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-P01',
        'location_name' => 'Lokasi PLN',
        'activity_type' => 'PLN_CONNECTION',
        'subcontractor_code' => 'SUB-001',
        'status' => 'REPORTED',
        'kwh_meter_installation_date' => '2025-03-10',
        'type_rate' => 'B2',
        'id_pelanggan' => '123456789',
        'nidi_slo_date_acquired' => '2025-02-20',
    ]]);

    $tempPath = 'temp/manual-import/pln.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath]);

    $data = AssignmentPlnData::first();
    expect($data)->not->toBeNull()
        ->and($data->type_rate)->toBe('B2')
        ->and($data->id_pelanggan)->toBe('123456789')
        ->and($data->kwh_meter_installation_date->format('Y-m-d'))->toBe('2025-03-10');
});

test('store skips error rows but still imports valid rows', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([
        // Valid row
        [
            'project_name' => 'Proyek A',
            'client_name' => 'PLN',
            'main_contractor_name' => 'PT Kontraktor A',
            'site_code' => 'SITE-OK',
            'location_name' => 'Lokasi OK',
            'activity_type' => 'SURVEY',
            'subcontractor_code' => 'SUB-001',
            'status' => 'REPORTED',
        ],
        // Invalid row — bad activity_type
        [
            'project_name' => 'Proyek A',
            'client_name' => 'PLN',
            'main_contractor_name' => 'PT Kontraktor A',
            'site_code' => 'SITE-OK',
            'location_name' => 'Lokasi OK',
            'activity_type' => 'INVALID',
            'subcontractor_code' => 'SUB-001',
        ],
    ]);

    $tempPath = 'temp/manual-import/mixed.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath])
        ->assertRedirect(route('admin.projects.index'));

    // Valid row was imported, bad row was skipped.
    expect(Assignment::count())->toBe(1)
        ->and(Assignment::first()->activity_type)->toBe(ActivityType::Survey);
});

test('store upserts into existing project instead of creating a duplicate', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    // Pre-existing project with the same name.
    $existing = Project::factory()->create(['name' => 'Proyek Lama', 'main_contractor_id' => $mc->id]);

    $csv = buildCsv([[
        'project_name' => 'Proyek Lama',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-NEW',
        'location_name' => 'Lokasi Baru',
        'activity_type' => 'BAST',
        'subcontractor_code' => 'SUB-001',
        'status' => 'REPORTED',
    ]]);

    $tempPath = 'temp/manual-import/upsert.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath]);

    // No duplicate project should be created.
    expect(Project::count())->toBe(1)
        ->and(Site::where('project_id', $existing->id)->exists())->toBeTrue();
});

test('store defaults status to REPORTED when status column is empty', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek Default',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-D01',
        'location_name' => 'Lokasi',
        'activity_type' => 'SURVEY',
        'subcontractor_code' => 'SUB-001',
        'status' => '', // empty — should default to REPORTED
    ]]);

    $tempPath = 'temp/manual-import/default-status.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath]);

    expect(Assignment::first()->status)->toBe(AssignmentStatus::Reported);
});

test('store rejects path traversal in temp_path', function () {
    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), [
            'temp_path' => '../../etc/passwd',
        ])
        ->assertStatus(422);
});

test('store rejects missing temp file', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), [
            'temp_path' => 'temp/manual-import/nonexistent.csv',
        ])
        ->assertStatus(422);
});

// ── Legacy Reports ─────────────────────────────────────────────────────────────

test('superadmin can upload a legacy report to an assignment', function () {
    Storage::fake('public');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $assignment = Assignment::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.assignments.legacy-reports.store', $assignment), [
            'report_type' => 'SSR',
            'file' => UploadedFile::fake()->create('report.pdf', 500, 'application/pdf'),
        ])
        ->assertRedirect();

    expect(AssignmentLegacyReport::where('assignment_id', $assignment->id)->count())->toBe(1);

    $report = AssignmentLegacyReport::first();
    expect($report->report_type)->toBe('SSR')
        ->and($report->original_filename)->toBe('report.pdf')
        ->and($report->uploaded_by)->toBe($superAdmin->id);
});

test('non-superadmin cannot upload a legacy report', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $assignment = Assignment::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.assignments.legacy-reports.store', $assignment), [
            'report_type' => 'SSR',
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('superadmin can delete a legacy report and its file is removed from storage', function () {
    Storage::fake('public');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $assignment = Assignment::factory()->create();

    $filePath = 'legacy-reports/test.pdf';
    Storage::disk('public')->put($filePath, 'fake-content');

    $report = AssignmentLegacyReport::create([
        'assignment_id' => $assignment->id,
        'report_type' => 'BAST',
        'file_path' => $filePath,
        'original_filename' => 'test.pdf',
        'uploaded_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('admin.assignments.legacy-reports.destroy', [$assignment, $report]))
        ->assertRedirect();

    expect(AssignmentLegacyReport::find($report->id))->toBeNull();
    Storage::disk('public')->assertMissing($filePath);
});

// ── Date format handling ───────────────────────────────────────────────────────

test('store accepts DD/MM/YYYY date format (Indonesian Excel locale)', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek Tanggal',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'project_start_date' => '01/01/2025',   // DD/MM/YYYY
        'project_end_date' => '31/12/2026',      // DD/MM/YYYY — this was the crashing input
        'site_code' => 'SITE-DATE',
        'location_name' => 'Lokasi',
        'activity_type' => 'CONSTRUCTION',
        'subcontractor_code' => 'SUB-001',
        'status' => 'REPORTED',
        'cons_actual_start_date' => '01/02/2025',
        'cons_actual_done_date' => '28/03/2025',
        'go_live_date_pln' => '05/04/2025',
    ]]);

    $tempPath = 'temp/manual-import/date-format.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath])
        ->assertRedirect(route('admin.projects.index'));

    $project = Project::where('name', 'Proyek Tanggal')->first();
    expect($project)->not->toBeNull()
        ->and($project->start_date->format('Y-m-d'))->toBe('2025-01-01')
        ->and($project->end_date->format('Y-m-d'))->toBe('2026-12-31');

    $data = AssignmentConstructionData::first();
    expect($data->cons_actual_start_date->format('Y-m-d'))->toBe('2025-02-01')
        ->and($data->cons_actual_done_date->format('Y-m-d'))->toBe('2025-03-28')
        ->and($data->go_live_date_pln->format('Y-m-d'))->toBe('2025-04-05');
});

test('preview flags unparseable date format as error', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);

    $csv = buildCsv([[
        'project_name' => 'Test',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-BAD',
        'location_name' => 'Lokasi',
        'activity_type' => 'SURVEY',
        'project_start_date' => '2025-13-01',   // month 13 — invalid
    ]]);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.preview'), ['file' => csvFile($csv)])
        ->assertInertia(fn ($page) => $page
            ->where('previewSummary.errors', 1)
            ->where('previewSummary.ok', 0)
        );
});

test('store accepts YYYY-MM-DD date format and stores correctly', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mc = MainContractor::factory()->create(['name' => 'PT Kontraktor A']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach($mc->id);

    $csv = buildCsv([[
        'project_name' => 'Proyek ISO Date',
        'client_name' => 'PLN',
        'main_contractor_name' => 'PT Kontraktor A',
        'site_code' => 'SITE-ISO',
        'location_name' => 'Lokasi',
        'activity_type' => 'PLN_CONNECTION',
        'subcontractor_code' => 'SUB-001',
        'kwh_meter_installation_date' => '2025-03-15',
        'nidi_slo_date_acquired' => '2025-02-28',
    ]]);

    $tempPath = 'temp/manual-import/iso-date.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath]);

    $data = AssignmentPlnData::first();
    expect($data->kwh_meter_installation_date->format('Y-m-d'))->toBe('2025-03-15')
        ->and($data->nidi_slo_date_acquired->format('Y-m-d'))->toBe('2025-02-28');
});

test('same project name under different EPCs creates separate projects', function () {
    Storage::fake('local');

    $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
    $mcA = MainContractor::factory()->create(['name' => 'PT EPC Alpha']);
    $mcB = MainContractor::factory()->create(['name' => 'PT EPC Beta']);
    Client::factory()->create(['name' => 'PLN']);
    $sub = Subcontractor::factory()->create(['code' => 'SUB-001']);
    $sub->mainContractors()->attach([$mcA->id, $mcB->id]);

    $csv = buildCsv([
        [
            'project_name' => 'Proyek Sama',
            'client_name' => 'PLN',
            'main_contractor_name' => 'PT EPC Alpha',
            'site_code' => 'SITE-A01',
            'location_name' => 'Lokasi Alpha',
            'activity_type' => 'SURVEY',
            'subcontractor_code' => 'SUB-001',
            'status' => 'REPORTED',
        ],
        [
            'project_name' => 'Proyek Sama',
            'client_name' => 'PLN',
            'main_contractor_name' => 'PT EPC Beta',
            'site_code' => 'SITE-B01',
            'location_name' => 'Lokasi Beta',
            'activity_type' => 'SURVEY',
            'subcontractor_code' => 'SUB-001',
            'status' => 'REPORTED',
        ],
    ]);

    $tempPath = 'temp/manual-import/same-name-diff-epc.csv';
    Storage::disk('local')->put($tempPath, $csv);

    $this->actingAs($superAdmin)
        ->post(route('admin.manual-import.store'), ['temp_path' => $tempPath]);

    // Two separate projects must be created, one per EPC.
    expect(Project::count())->toBe(2);

    $projectAlpha = Project::where('main_contractor_id', $mcA->id)->first();
    $projectBeta = Project::where('main_contractor_id', $mcB->id)->first();

    expect($projectAlpha)->not->toBeNull()
        ->and($projectBeta)->not->toBeNull()
        ->and($projectAlpha->id)->not->toBe($projectBeta->id);

    // Each site belongs to its own EPC's project.
    expect(Site::where('site_code', 'SITE-A01')->first()->project_id)->toBe($projectAlpha->id)
        ->and(Site::where('site_code', 'SITE-B01')->first()->project_id)->toBe($projectBeta->id);
});
