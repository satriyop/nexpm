<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;

class DbSchemaService
{
    /** Tables the full-mode agent is allowed to query. */
    public const ALLOWED_TABLES = [
        'assignments',
        'sites',
        'projects',
        'users',
        'subcontractors',
        'main_contractors',
        'clients',
        'assignment_survey_data',
        'assignment_construction_data',
        'assignment_bast_data',
        'assignment_pln_data',
        'reports',
        'report_assignments',
        'site_types',
        'machine_types',
        'assignment_audit_logs',
    ];

    public function buildSchemaDescription(int $mainContractorId): string
    {
        $lines = [];

        foreach (self::ALLOWED_TABLES as $table) {
            try {
                $columns = DB::select("SHOW COLUMNS FROM `{$table}`");
                $columnNames = array_map(fn ($col) => $col->Field, $columns);
                $lines[] = "Table: {$table}";
                $lines[] = 'Columns: '.implode(', ', $columnNames);
                $lines[] = '';
            } catch (\Throwable) {
                // Table may not exist in this environment; skip it
            }
        }

        $lines[] = "IMPORTANT: Always scope queries with the relevant main_contractor_id = {$mainContractorId}.";
        $lines[] = 'Projects, sites, and assignments all belong to a main_contractor via the projects.main_contractor_id column.';
        $lines[] = 'Use JOINs: assignments JOIN sites ON assignments.site_id = sites.id JOIN projects ON sites.project_id = projects.id WHERE projects.main_contractor_id = '.$mainContractorId;

        return implode("\n", $lines);
    }
}
