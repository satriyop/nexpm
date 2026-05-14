<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ManualProjectImport;
use App\Services\ManualProjectCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ManualProjectImportController extends Controller
{
    public function __construct(private readonly ManualProjectCsvImportService $service) {}

    public function index(): InertiaResponse
    {
        $this->authorizeSuperAdmin();

        return Inertia::render('admin/manual-import/Index');
    }

    public function template(): Response
    {
        $this->authorizeSuperAdmin();

        $sep = ';';
        $handle = fopen('php://memory', 'w');

        fputcsv($handle, ManualProjectImport::COLUMNS, $sep);

        // Example row covering all activity types — one row per assignment.
        $examples = [
            [
                'PLN Jawa Barat 2025', 'PLN', 'PT Kontraktor A', '2025-01-01', '2025-12-31',
                'SITE-001', 'Lokasi A', 'Jl. Merdeka 1', 'Jawa Barat', 'Bandung',
                'https://maps.google.com/?q=-6.9,107.6',
                'EVCS', '', '50kVA', 'Budi Santoso', 'SS-WO-2025-001', '15', '2',
                'SURVEY', 'SUB-001', 'REPORTED',
                '2025-01-10', '2025-01-15', 'Underground', '5',
                '', '', '', '', '', '', '',
                '', '', '', '', '',
                '', '',
            ],
            [
                'PLN Jawa Barat 2025', 'PLN', 'PT Kontraktor A', '2025-01-01', '2025-12-31',
                'SITE-001', 'Lokasi A', 'Jl. Merdeka 1', 'Jawa Barat', 'Bandung',
                '', '', '', '', '', '', '', '',
                'CONSTRUCTION', 'SUB-001', 'REPORTED',
                '', '', '', '',
                'CONS-WO-001', '2025-02-01', '2025-02-05', '2025-03-01', 'SN-12345', '2025-03-05', '',
                '', '', '', '', '',
                '', '',
            ],
            [
                'PLN Jawa Barat 2025', 'PLN', 'PT Kontraktor A', '2025-01-01', '2025-12-31',
                'SITE-001', 'Lokasi A', 'Jl. Merdeka 1', 'Jawa Barat', 'Bandung',
                '', '', '', '', '', '', '', '',
                'PLN_CONNECTION', 'SUB-001', 'REPORTED',
                '', '', '', '',
                '', '', '', '', '', '', '',
                '2025-03-10', 'B2', '123456789', '2025-02-20', '2025-02-25',
                '', '',
            ],
            [
                'PLN Jawa Barat 2025', 'PLN', 'PT Kontraktor A', '2025-01-01', '2025-12-31',
                'SITE-001', 'Lokasi A', 'Jl. Merdeka 1', 'Jawa Barat', 'Bandung',
                '', '', '', '', '', '', '', '',
                'BAST', 'SUB-001', 'REPORTED',
                '', '', '', '',
                '', '', '', '', '', '', '',
                '', '', '', '', '',
                'Telkomsel', '08123456789',
            ],
        ];

        foreach ($examples as $example) {
            fputcsv($handle, $example, $sep);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="manual-project-import-template.csv"',
        ]);
    }

    public function preview(Request $request): InertiaResponse
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $tempPath = $request->file('file')->store('temp/manual-import', 'local');

        $result = $this->service->preview(Storage::disk('local')->path($tempPath));

        return Inertia::render('admin/manual-import/Index', [
            'previewRows' => $result['rows'],
            'previewSummary' => $result['summary'],
            'tempPath' => $tempPath,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'temp_path' => ['required', 'string'],
        ]);

        $tempPath = $request->string('temp_path')->toString();

        // Prevent path traversal — only allow paths within the expected temp directory.
        abort_unless(
            str_starts_with($tempPath, 'temp/manual-import/'),
            422,
            'Invalid file path.'
        );

        $fullPath = Storage::disk('local')->path($tempPath);

        abort_unless(file_exists($fullPath), 422, 'Uploaded file no longer exists. Please re-upload.');

        $result = $this->service->import($fullPath);

        Storage::disk('local')->delete($tempPath);

        $message = sprintf(
            'Import complete: %d project(s), %d site(s), %d assignment(s) created.',
            $result['created_projects'],
            $result['created_sites'],
            $result['created_assignments'],
        );

        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' row(s) had errors and were skipped.';
        }

        return redirect()->route('admin.projects.index')->with('success', $message);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless($this->currentUser()->isSuperAdmin(), 403, 'Super admin access required.');
    }
}
