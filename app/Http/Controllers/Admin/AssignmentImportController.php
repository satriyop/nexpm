<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Subcontractor;
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
        $siteCodes = $project->sites()->orderBy('site_code')->pluck('site_code');
        $subcontractors = Subcontractor::query()
            ->where('main_contractor_id', $project->main_contractor_id)
            ->orderBy('name')
            ->get(['name', 'code']);

        $activityTypes = ['SURVEY', 'CONSTRUCTION', 'PLN_CONNECTION', 'BAST'];

        // Use semicolon separator so Indonesian-locale Excel auto-splits into columns.
        // The import service detects the delimiter automatically.
        $sep = ';';
        $handle = fopen('php://memory', 'w');

        // Reference comment rows (pure ASCII to avoid encoding issues)
        fputcsv($handle, ['# Available subcontractor codes:'], $sep);
        foreach ($subcontractors as $sc) {
            fputcsv($handle, ["# {$sc->code} - {$sc->name}"], $sep);
        }

        fputcsv($handle, ['site_code', 'activity_type', 'subcontractor_code'], $sep);

        if ($siteCodes->isEmpty()) {
            foreach ($activityTypes as $type) {
                fputcsv($handle, ['SITE-001', $type, ''], $sep);
            }
        } else {
            foreach ($siteCodes as $code) {
                foreach ($activityTypes as $type) {
                    fputcsv($handle, [$code, $type, ''], $sep);
                }
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'assignments-import-'.str($project->name)->slug().'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
