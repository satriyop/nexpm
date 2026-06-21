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
        'main_contractor_subcontractor',
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
- "Subkontraktor" / subcontractor companies → query `subcontractors` table (has: id, name, code, phone, email, pic). NO main_contractor_id column — use the pivot table instead.
- "User subkontraktor" / individuals working for a subcontractor → query `users` WHERE role = 'subcontractor' (each user has subcontractor_id FK)
- "Main contractor" / contractor company → query `main_contractors` table (has: id, name)
- "User admin / main contractor staff" → query `users` WHERE role IN ('admin', 'super_admin')
- "Assignment" / work order per site per activity → query `assignments` (activity_type: SURVEY | PLN_CONNECTION | CONSTRUCTION | BAST; status: PENDING | REVISION | DOCUMENT | VERIFIED | REPORTED | DROP | SURVEY | CONSTRUCTION | MACHINE_ONSITE | DONE | LIVE | REGISTRATION | BILLING | CONNECTION | KWH_DONE | SUBMITTED)
- "Site" / location → query `sites`

SCOPING RULES — always filter to main_contractor_id = {$mcId}:
- `subcontractors`:    JOIN main_contractor_subcontractor mcs ON mcs.subcontractor_id = subcontractors.id WHERE mcs.main_contractor_id = {$mcId}
- `users`:             WHERE users.main_contractor_id = {$mcId}
- `projects`:          WHERE projects.main_contractor_id = {$mcId}
- `sites`:             JOIN projects ON sites.project_id = projects.id WHERE projects.main_contractor_id = {$mcId}
- `assignments`:       JOIN sites ON assignments.site_id = sites.id JOIN projects ON sites.project_id = projects.id WHERE projects.main_contractor_id = {$mcId}

IMPORTANT: `assignments.subcontractor_id` is a direct FK to `subcontractors.id` — do NOT join through `users`.

COMMON QUERY PATTERNS:
- List all subcontractor companies:
  SELECT s.id, s.name, s.code FROM subcontractors s JOIN main_contractor_subcontractor mcs ON mcs.subcontractor_id = s.id WHERE mcs.main_contractor_id = {$mcId}

- Count sites (lokasi) for a named project:
  SELECT COUNT(*) AS total_sites FROM sites JOIN projects ON sites.project_id = projects.id WHERE projects.main_contractor_id = {$mcId} AND projects.name LIKE '%planet ban%'

- Count pending SURVEY assignments for a named subcontractor:
  SELECT COUNT(*) AS total FROM assignments a JOIN subcontractors s ON a.subcontractor_id = s.id JOIN main_contractor_subcontractor mcs ON mcs.subcontractor_id = s.id JOIN sites ON a.site_id = sites.id JOIN projects ON sites.project_id = projects.id WHERE mcs.main_contractor_id = {$mcId} AND s.name LIKE '%ade ahyadi%' AND a.activity_type = 'SURVEY' AND a.status = 'PENDING'

- Count assignments by activity type (e.g. PLN):
  SELECT COUNT(*) AS total, a.status FROM assignments a JOIN sites ON a.site_id = sites.id JOIN projects ON sites.project_id = projects.id WHERE projects.main_contractor_id = {$mcId} AND a.activity_type = 'PLN_CONNECTION' GROUP BY a.status

- List outstanding assignments for a subcontractor (for reminders):
  SELECT a.id, a.activity_type, a.status, a.updated_at, sites.site_code, sites.location_name, projects.name AS project_name FROM assignments a JOIN subcontractors s ON a.subcontractor_id = s.id JOIN main_contractor_subcontractor mcs ON mcs.subcontractor_id = s.id JOIN sites ON a.site_id = sites.id JOIN projects ON sites.project_id = projects.id WHERE mcs.main_contractor_id = {$mcId} AND s.name LIKE '%asep%' AND a.status NOT IN ('VERIFIED', 'REPORTED', 'DROP') ORDER BY a.updated_at ASC LIMIT {$maxRows}

- List all users who are subcontractors:
  SELECT u.id, u.name, s.name AS company FROM users u JOIN subcontractors s ON u.subcontractor_id = s.id WHERE u.main_contractor_id = {$mcId} AND u.role = 'subcontractor'
NOTES;

        return implode("\n", $lines);
    }
}
