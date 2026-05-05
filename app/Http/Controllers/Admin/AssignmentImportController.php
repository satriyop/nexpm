<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\AssignmentCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssignmentImportController extends Controller
{
    public function __construct(private readonly AssignmentCsvImportService $service) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();

        $result = $this->service->import($path, $project->id);

        return back()->with('import', [
            'type' => 'assignments',
            'project_id' => $project->id,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'errors' => $result['errors'],
        ]);
    }

    public function template(Project $project): Response
    {
        $rows = [
            ['site_code', 'activity_type', 'subcontractor_code'],
            ['SITE-001', 'SURVEY', 'SUBCON-001'],
            ['SITE-001', 'CONSTRUCTION', 'SUBCON-001'],
            ['SITE-001', 'PLN_CONNECTION', 'SUBCON-002'],
            ['SITE-001', 'BAST', 'SUBCON-001'],
        ];

        $csv = implode("\n", array_map(fn ($row) => implode(',', $row), $rows)) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="assignments-import-template.csv"',
        ]);
    }
}
