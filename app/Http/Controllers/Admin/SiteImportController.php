<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\SiteMasterDataImport;
use App\Models\Project;
use App\Services\SiteCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SiteImportController extends Controller
{
    public function __construct(private readonly SiteCsvImportService $service) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();

        $result = $this->service->import($path, $project->id);

        return back()->with('import', [
            'type' => 'sites',
            'project_id' => $project->id,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'errors' => $result['errors'],
        ]);
    }

    public function template(Project $project): Response
    {
        $headers = SiteMasterDataImport::COLUMNS;

        $example = [
            'SITE-001',
            'Mall Taman Anggrek',
            'Jl. Letjen S. Parman Kav. 21, RT.12/RW.1',
            'DKI Jakarta',
            'Jakarta Barat',
            'https://maps.google.com/?q=-6.178,106.796',
            '-6.178306',
            '106.795867',
            'EVCS',
            '',
            'Budi Santoso',
            'SS-WO-2026-001',
            '15.5',
            '2',
        ];

        $handle = fopen('php://memory', 'w');
        fputcsv($handle, $headers);
        fputcsv($handle, $example);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sites-import-template.csv"',
        ]);
    }
}
