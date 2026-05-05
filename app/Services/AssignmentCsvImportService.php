<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\Site;
use App\Models\Subcontractor;

class AssignmentCsvImportService
{
    /**
     * Imports a sub-contractor assignment CSV file scoped to a project.
     *
     * Expected columns (with header row):
     *  site_code, activity_type, subcontractor_code
     *
     * @return array{created: int, updated: int, errors: array<int, string>}
     */
    public function import(string $filePath, int $projectId): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['Unable to open CSV file.']];
        }

        try {
            // Skip header.
            fgetcsv($handle, escape: '\\');

            $rowNumber = 1;
            while (($data = fgetcsv($handle, escape: '\\')) !== false) {
                $rowNumber++;

                if ($this->isEmpty($data)) {
                    continue;
                }

                $siteCode = isset($data[0]) ? trim((string) $data[0]) : '';
                $activityRaw = isset($data[1]) ? trim((string) $data[1]) : '';
                $subcontractorCode = isset($data[2]) ? trim((string) $data[2]) : '';

                if ($siteCode === '' || $activityRaw === '' || $subcontractorCode === '') {
                    $errors[] = "Row {$rowNumber}: site_code, activity_type, and subcontractor_code are required.";

                    continue;
                }

                $site = Site::query()
                    ->where('site_code', $siteCode)
                    ->where('project_id', $projectId)
                    ->first();

                if ($site === null) {
                    $errors[] = "Row {$rowNumber}: site '{$siteCode}' not found in project.";

                    continue;
                }

                $activityType = ActivityType::tryFrom($activityRaw);
                if ($activityType === null) {
                    $errors[] = "Row {$rowNumber}: invalid activity_type '{$activityRaw}'.";

                    continue;
                }

                $subcontractor = Subcontractor::query()
                    ->with('subcontractorType')
                    ->where('code', $subcontractorCode)
                    ->first();
                if ($subcontractor === null) {
                    $errors[] = "Row {$rowNumber}: subcontractor '{$subcontractorCode}' not found.";

                    continue;
                }

                if ($subcontractor->subcontractorType !== null && ! $subcontractor->subcontractorType->handlesActivity($activityType)) {
                    $errors[] = "Row {$rowNumber}: subcontractor '{$subcontractorCode}' (type: {$subcontractor->subcontractorType->name}) cannot handle {$activityType->value} assignments.";

                    continue;
                }

                $existing = Assignment::query()
                    ->where('site_id', $site->id)
                    ->where('activity_type', $activityType->value)
                    ->first();

                if ($existing !== null) {
                    $existing->subcontractor_id = $subcontractor->id;
                    $existing->save();
                    $updated++;

                    $this->ensureActivityDataRecord($existing, $activityType);

                    continue;
                }

                $assignment = Assignment::query()->create([
                    'site_id' => $site->id,
                    'subcontractor_id' => $subcontractor->id,
                    'activity_type' => $activityType,
                    'status' => AssignmentStatus::Pending,
                ]);

                $this->ensureActivityDataRecord($assignment, $activityType);

                $created++;
            }
        } finally {
            fclose($handle);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    private function ensureActivityDataRecord(Assignment $assignment, ActivityType $activityType): void
    {
        match ($activityType) {
            ActivityType::Survey => AssignmentSurveyData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
            ActivityType::PlnConnection => AssignmentPlnData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
            ActivityType::Construction => AssignmentConstructionData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
            ActivityType::Bast => AssignmentBastData::query()->firstOrCreate(['assignment_id' => $assignment->id]),
        };
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
