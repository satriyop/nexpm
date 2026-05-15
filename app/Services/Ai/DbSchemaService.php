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

        $mcId = $mainContractorId;
        $lines[] = <<<NOTES
== DOMAIN MODEL (read carefully before writing queries) ==

ENTITY CONCEPTS — use the correct table for each concept:
- "Subkontraktor" / subcontractor companies → query `subcontractors` table (has: id, main_contractor_id, name, code, phone, email, pic)
- "User subkontraktor" / individuals working for a subcontractor → query `users` WHERE role = 'subcontractor' (each user has subcontractor_id FK)
- "Main contractor" / contractor company → query `main_contractors` table (has: id, name)
- "User admin / main contractor staff" → query `users` WHERE role IN ('admin', 'super_admin')
- "Assignment" / work order per site per activity → query `assignments`
- "Site" / location → query `sites`

SCOPING RULES — always filter to main_contractor_id = {$mcId}:
- `subcontractors`:    WHERE subcontractors.main_contractor_id = {$mcId}
- `users`:             WHERE users.main_contractor_id = {$mcId}
- `projects`:          WHERE projects.main_contractor_id = {$mcId}
- `sites`:             JOIN projects ON sites.project_id = projects.id WHERE projects.main_contractor_id = {$mcId}
- `assignments`:       JOIN sites ON assignments.site_id = sites.id JOIN projects ON sites.project_id = projects.id WHERE projects.main_contractor_id = {$mcId}

COMMON QUERY PATTERNS:
- List all subcontractor companies:
  SELECT id, name, code FROM subcontractors WHERE main_contractor_id = {$mcId}
- List all users who are subcontractors:
  SELECT u.id, u.name, s.name AS company FROM users u JOIN subcontractors s ON u.subcontractor_id = s.id WHERE u.main_contractor_id = {$mcId} AND u.role = 'subcontractor'
- Count assignments per subcontractor company:
  SELECT s.name, COUNT(a.id) AS total FROM subcontractors s LEFT JOIN users u ON u.subcontractor_id = s.id AND u.role = 'subcontractor' LEFT JOIN assignments a ON a.subcontractor_id = u.id WHERE s.main_contractor_id = {$mcId} GROUP BY s.id, s.name
NOTES;

        return implode("\n", $lines);
    }
}
