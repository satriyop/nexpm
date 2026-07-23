<?php

namespace App\Services\Dashboard;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SiteFlowEvaluator
{
    /**
     * @param  Collection<int, object>  $assignments
     * @param  Collection<int, string>  $mainContractorAdminOwners
     * @return array{issues: Collection<int, array<string, mixed>>, primary_issue: ?array<string, mixed>, overall_status: string, current_stage: ?array<string, mixed>, flow_explanation: string}
     */
    public function evaluate(object $site, Collection $assignments, Collection $mainContractorAdminOwners): array
    {
        $activeAssignments = $assignments->whereNotIn('status', [AssignmentStatus::Drop->value]);
        $issues = $this->siteIssues($site, $assignments, $mainContractorAdminOwners);
        $primaryIssue = $issues->first();
        $overallStatus = $this->overallStatus($assignments, $activeAssignments, $primaryIssue);
        $currentStage = $this->currentStage($assignments, $primaryIssue);

        return [
            'issues' => $issues,
            'primary_issue' => $primaryIssue,
            'overall_status' => $overallStatus,
            'current_stage' => $currentStage,
            'flow_explanation' => $this->flowExplanation($assignments, $primaryIssue, $currentStage, $overallStatus),
        ];
    }

    public function defaultIssueText(string $overallStatus): string
    {
        return match ($overallStatus) {
            'done' => 'All active assignments for this location are verified or reported.',
            'dropped' => 'All assignments for this location are dropped.',
            'in_progress' => 'Work is in progress with no critical blocker detected.',
            default => 'No active issue detected.',
        };
    }

    public function defaultActionText(string $overallStatus): string
    {
        return match ($overallStatus) {
            'done' => 'No action needed.',
            'dropped' => 'Confirm site scope if it should be restored.',
            'in_progress' => 'Monitor workstream updates.',
            default => 'Review this location.',
        };
    }

    public function statusSortScore(string $overallStatus): int
    {
        return match ($overallStatus) {
            'blocked' => 95,
            'stalled' => 80,
            'needs_review' => 58,
            'ready_for_report' => 52,
            'not_started' => 45,
            'in_progress' => 30,
            'dropped' => 10,
            'done' => 0,
            default => 0,
        };
    }

    /**
     * @param  Collection<int, object>  $assignments
     * @param  Collection<int, string>  $mainContractorAdminOwners
     * @return Collection<int, array<string, mixed>>
     */
    private function siteIssues(object $site, Collection $assignments, Collection $mainContractorAdminOwners): Collection
    {
        if ($assignments->whereNotNull('assignment_id')->isEmpty()) {
            return collect([
                $this->issue($site, null, 'medium', 45, 'no_assignment_started', 'No assignment has started for this location.', 'Main Contractor Admin', 'Create or assign the required workstreams for this site.', 'setup', 'workflow', 'assignment_absence', 'site', [ActivityType::Survey->value, ActivityType::PlnConnection->value, ActivityType::Construction->value, ActivityType::Bast->value]),
            ]);
        }

        $activeAssignments = $assignments->whereNotIn('status', [AssignmentStatus::Drop->value]);

        if ($activeAssignments->isEmpty()) {
            return collect([
                $this->issue($site, $assignments->first(), 'low', 10, 'site_dropped', 'All assignments for this site are dropped.', 'Main Contractor Admin', 'Confirm whether the site should remain dropped or be restored.', 'scope', 'workflow', 'terminal_status', 'site'),
            ]);
        }

        return $activeAssignments
            ->flatMap(fn (object $row): array => $this->issuesForAssignment($site, $row, $mainContractorAdminOwners))
            ->sortByDesc('severity_score')
            ->values();
    }

    /**
     * @param  Collection<int, string>  $mainContractorAdminOwners
     * @return list<array<string, mixed>>
     */
    private function issuesForAssignment(object $site, object $row, Collection $mainContractorAdminOwners): array
    {
        $items = [];
        $ageDays = $row->updated_at ? (int) Carbon::parse($row->updated_at)->diffInDays(now()) : 0;
        $mainContractorOwner = $this->mainContractorOwner($site, $mainContractorAdminOwners);

        $items = [
            ...$items,
            ...$this->surveyIssues($site, $row, $mainContractorOwner),
            ...$this->plnIssues($site, $row),
            ...$this->constructionIssues($site, $row, $mainContractorOwner),
            ...$this->bastIssues($site, $row),
            ...$this->noteIssues($site, $row),
        ];

        if (
            $row->activity_type === ActivityType::Construction->value
            && blank($row->cons_wo_number)
            && ! in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value], true)
        ) {
            $items[] = $this->issue($site, $row, 'critical', 95, 'construction_missing_wo', 'Construction cannot proceed because WO number is missing.', $mainContractorOwner, 'Fill the construction WO number, then follow up the subcontractor.', 'construction', 'data_gap', 'structured_data', 'construction', [ActivityType::Bast->value], ['cons_wo_number' => null]);
        }

        if ($row->status === AssignmentStatus::Revision->value) {
            $items[] = $this->issue($site, $row, 'high', 78, 'revision_pending', 'Assignment is in revision and needs correction.', $row->subcontractor_name ?? 'Subcontractor', 'Close the revision comments and resubmit for review.', $row->activity_type, 'revision', 'workflow_status', $row->activity_type, $this->downstreamStages($row->activity_type));
        }

        if (in_array($row->status, [AssignmentStatus::Pending->value, AssignmentStatus::Revision->value], true) && $ageDays >= 7) {
            $items[] = $this->issue($site, $row, $ageDays >= 14 ? 'critical' : 'high', $ageDays >= 14 ? 90 : 72, 'stalled_assignment', "{$row->status} has had no update for {$ageDays} days.", $row->status === AssignmentStatus::Revision->value ? ($row->subcontractor_name ?? 'Subcontractor') : $mainContractorOwner, 'Follow up the owner and force a progress update this week.', $row->activity_type, 'staleness', 'age_rule', $row->activity_type, $this->downstreamStages($row->activity_type), ['age_days' => $ageDays]);
        }

        if (in_array($row->status, array_map(fn (AssignmentStatus $status): string => $status->value, AssignmentStatus::verifiableStatuses()), true)) {
            $items[] = $this->issue($site, $row, 'medium', 58, 'ready_for_verification', "{$row->activity_type} is ready for admin verification.", $mainContractorOwner, 'Review data quality and verify when valid.', $row->activity_type, 'review_queue', 'workflow_status', $row->activity_type);
        }

        if ($row->status === AssignmentStatus::Verified->value) {
            $items[] = $this->issue($site, $row, 'medium', 52, 'verified_not_reported', "{$row->activity_type} is verified but not reported.", $mainContractorOwner, 'Include this assignment in the next report generation.', $row->activity_type, 'report_queue', 'workflow_status', $row->activity_type);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function noteIssues(object $site, object $row): array
    {
        if ($this->isTerminal($row) || ! is_array($row->latest_comment ?? null)) {
            return [];
        }

        $body = (string) ($row->latest_comment['body'] ?? '');

        if (! $this->looksLikeBlockerNote($body)) {
            return [];
        }

        return [
            $this->issue($site, $row, 'medium', 64, 'note_blocker_signal', 'Latest note may explain the blocker: '.$body, $row->latest_comment['user']['name'] ?? ($row->subcontractor_name ?? 'Owner'), 'Review the latest note and update the structured blocker/status if needed.', $row->activity_type, 'field_note', 'field_note', $row->activity_type, $this->downstreamStages($row->activity_type), ['latest_note' => $body]),
        ];
    }

    private function looksLikeBlockerNote(string $body): bool
    {
        $normalized = mb_strtolower($body);

        foreach (['block', 'blocked', 'issue', 'problem', 'waiting', 'wait', 'hold', 'stuck', 'late', 'delay', 'akses', 'access', 'izin', 'tutup', 'pln', 'wo', 'revisi', 'kendala', 'belum', 'pending'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function surveyIssues(object $site, object $row, string $mainContractorOwner): array
    {
        if ($row->activity_type !== ActivityType::Survey->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $missingSchedule = $this->missingLabels($row, [
            'survey_schedule_date' => 'survey schedule',
        ]);
        $missingSiteData = $this->missingLabels((object) [
            'site_power_kva' => $site->power_kva,
            'survey_power_kva' => $row->survey_power_kva,
        ], [
            'site_power_kva' => 'site power KVA',
            'survey_power_kva' => 'survey power KVA',
        ]);
        $missingEvidence = $this->missingLabels($row, [
            'survey_photo_overall_site' => 'overall site photo',
            'survey_photo_parking_evcs' => 'EV parking photo',
            'survey_photo_access_route' => 'access route photo',
            'survey_photo_pln_network' => 'PLN network photo',
            'survey_photo_satellite_gmaps' => 'satellite map photo',
            'survey_file_site_plan' => 'site plan',
            'survey_file_ba_survey' => 'BA survey',
        ]);

        if ($missingSchedule !== []) {
            $issues[] = $this->issue($site, $row, 'high', 84, 'survey_schedule_missing', 'Survey schedule is missing.', $row->subcontractor_name ?? 'Subcontractor', 'Ask the subcontractor to set the survey date.', 'survey', 'data_gap', 'structured_data', 'survey', $this->downstreamStages(ActivityType::Survey->value), ['missing' => $missingSchedule]);
        }

        if ($missingSiteData !== []) {
            $issues[] = $this->issue($site, $row, 'high', 82, 'site_missing_power', 'Survey/location power data is missing: '.$this->labelList($missingSiteData).'.', $mainContractorOwner, 'Complete site and survey power KVA before document/report work.', 'survey', 'data_gap', 'structured_data', 'survey', $this->downstreamStages(ActivityType::Survey->value), ['missing' => $missingSiteData]);
        }

        if ($missingEvidence !== []) {
            $issues[] = $this->issue($site, $row, 'high', 76, 'survey_evidence_missing', 'Survey evidence is incomplete: '.$this->labelList($missingEvidence).'.', $row->subcontractor_name ?? 'Subcontractor', 'Upload the missing survey photos and documents.', 'survey', 'data_gap', 'structured_data', 'survey', $this->downstreamStages(ActivityType::Survey->value), ['missing' => $missingEvidence]);
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plnIssues(object $site, object $row): array
    {
        if ($row->activity_type !== ActivityType::PlnConnection->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $owner = $row->subcontractor_name ?? 'Subcontractor';

        $missingRegistration = $this->missingLabels($row, [
            'pln_file_reg' => 'registration file',
            'pln_email_bpujl_req_date' => 'BPUJL request date',
        ]);

        if (in_array($row->status, [AssignmentStatus::Pending->value, AssignmentStatus::Registration->value], true) && $missingRegistration !== []) {
            $issues[] = $this->issue($site, $row, 'high', 83, 'pln_registration_incomplete', 'PLN registration is incomplete: '.$this->labelList($missingRegistration).'.', $owner, 'Complete PLN registration evidence and BPUJL request.', 'pln', 'data_gap', 'structured_data', ActivityType::PlnConnection->value, $this->downstreamStages(ActivityType::PlnConnection->value), ['missing' => $missingRegistration]);
        }

        $missingBilling = $this->missingLabels($row, [
            'pln_bpujl_acquired_date' => 'BPUJL acquired date',
            'pln_file_pk' => 'PK file',
        ]);

        if (in_array($row->status, [AssignmentStatus::Billing->value, AssignmentStatus::Connection->value, AssignmentStatus::KwhDone->value], true) && $missingBilling !== []) {
            $issues[] = $this->issue($site, $row, 'high', 79, 'pln_billing_incomplete', 'PLN billing/PK evidence is incomplete: '.$this->labelList($missingBilling).'.', $owner, 'Complete BPUJL and PK evidence before connection closeout.', 'pln', 'data_gap', 'structured_data', ActivityType::PlnConnection->value, $this->downstreamStages(ActivityType::PlnConnection->value), ['missing' => $missingBilling]);
        }

        $missingKwh = $this->missingLabels($row, [
            'pln_kwh_meter_installation_date' => 'kWh installation date',
            'pln_id_pelanggan' => 'customer ID',
            'pln_foto_kwh' => 'kWh photo',
        ]);

        if (in_array($row->status, [AssignmentStatus::Connection->value, AssignmentStatus::KwhDone->value], true) && $missingKwh !== []) {
            $issues[] = $this->issue($site, $row, 'high', 81, 'pln_kwh_incomplete', 'PLN kWh closeout is incomplete: '.$this->labelList($missingKwh).'.', $owner, 'Complete kWh installation data, customer ID, and kWh photo.', 'pln', 'data_gap', 'structured_data', ActivityType::PlnConnection->value, $this->downstreamStages(ActivityType::PlnConnection->value), ['missing' => $missingKwh]);
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function constructionIssues(object $site, object $row, string $mainContractorOwner): array
    {
        if ($row->activity_type !== ActivityType::Construction->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $owner = $row->subcontractor_name ?? 'Subcontractor';

        if (filled($row->cons_wo_number)) {
            $missingExecution = $this->missingLabels($row, [
                'cons_actual_start_date' => 'actual start date',
                'cons_actual_done_date' => 'actual done date',
                'machine_serial_number' => 'machine serial number',
                'foto_machine_sn' => 'machine serial photo',
            ]);

            if (in_array($row->status, [AssignmentStatus::Construction->value, AssignmentStatus::MachineOnsite->value, AssignmentStatus::Done->value, AssignmentStatus::Live->value], true) && $missingExecution !== []) {
                $issues[] = $this->issue($site, $row, 'high', 80, 'construction_data_incomplete', 'Construction data is incomplete: '.$this->labelList($missingExecution).'.', $owner, 'Complete construction dates, machine serial data, and serial photo.', 'construction', 'data_gap', 'structured_data', 'construction', $this->downstreamStages(ActivityType::Construction->value), ['missing' => $missingExecution]);
            }

            if ((int) ($row->construction_photo_count ?? 0) === 0 && in_array($row->status, [AssignmentStatus::Done->value, AssignmentStatus::Live->value], true)) {
                $issues[] = $this->issue($site, $row, 'high', 77, 'construction_photos_missing', 'Construction is marked advanced but has no construction photos.', $owner, 'Upload construction progress/completion photos.', 'construction', 'evidence_gap', 'structured_data', 'construction', $this->downstreamStages(ActivityType::Construction->value), ['construction_photo_count' => 0]);
            }

            if ($row->status === AssignmentStatus::Done->value && blank($row->go_live_date_pln) && blank($row->go_live_date_pln_pass)) {
                $issues[] = $this->issue($site, $row, 'medium', 62, 'construction_not_live', 'Construction is done but go-live dates are missing.', $mainContractorOwner, 'Confirm go-live readiness and fill the PLN go-live date.', 'construction', 'workflow_gap', 'structured_data', 'construction', $this->downstreamStages(ActivityType::Construction->value));
            }
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bastIssues(object $site, object $row): array
    {
        if ($row->activity_type !== ActivityType::Bast->value || $this->isTerminal($row)) {
            return [];
        }

        $issues = [];
        $missingSim = $this->missingLabels($row, [
            'bast_sim_provider' => 'SIM provider',
            'bast_nomor_simcard' => 'SIM number',
        ]);

        if ((int) ($row->bast_sim_photo_count ?? 0) < 2) {
            $missingSim[] = 'SIM card photos';
        }

        if ($missingSim !== []) {
            $issues[] = $this->issue($site, $row, 'high', 78, 'bast_sim_missing', 'SIM card data/evidence is incomplete: '.$this->labelList($missingSim).'.', $row->subcontractor_name ?? 'Subcontractor', 'Complete SIM provider, SIM number, and SIM card checkpoint photos.', 'bast', 'data_gap', 'structured_data', 'bast', [], ['missing' => $missingSim]);
        }

        $missingCore = $this->missingLabels($row, [
            'bast_plant_name' => 'plant name',
            'bast_installation_date' => 'installation date',
            'bast_commissioning_date' => 'commissioning date',
        ]);

        if ($missingCore === [] && (int) ($row->bast_photo_count ?? 0) > 0) {
            return $issues;
        }

        $missing = $missingCore;

        if ((int) ($row->bast_photo_count ?? 0) === 0) {
            $missing[] = 'BAST photos';
        }

        $issues[] = $this->issue($site, $row, 'high', 75, 'bast_evidence_missing', 'BAST evidence is incomplete: '.$this->labelList($missing).'.', $row->subcontractor_name ?? 'Subcontractor', 'Complete BAST fields and upload required checkpoint photos.', 'bast', 'evidence_gap', 'structured_data', 'bast', [], ['missing' => $missing]);

        return $issues;
    }

    private function isTerminal(object $row): bool
    {
        return in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value, AssignmentStatus::Drop->value], true);
    }

    /**
     * @param  array<string, string>  $labelsByField
     * @return list<string>
     */
    private function missingLabels(object $row, array $labelsByField): array
    {
        $missing = [];

        foreach ($labelsByField as $field => $label) {
            if (blank($row->{$field} ?? null)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $labels
     */
    private function labelList(array $labels): string
    {
        return collect($labels)->take(4)->join(', ');
    }

    /**
     * @param  list<string>  $blocksDownstream
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function issue(object $site, ?object $row, string $severity, int $score, string $type, string $problem, string $owner, string $recommendedAction, string $stage, string $category, string $source, string $blocksStage, array $blocksDownstream = [], array $evidence = []): array
    {
        return [
            'severity' => $severity,
            'severity_score' => $score,
            'type' => $type,
            'problem' => $problem,
            'owner' => $owner,
            'recommended_action' => $recommendedAction,
            'assignment_id' => $row !== null && isset($row->assignment_id) ? (int) $row->assignment_id : null,
            'activity_type' => $row?->activity_type,
            'status' => $row?->status,
            'age_days' => $row?->updated_at ? (int) Carbon::parse($row->updated_at)->diffInDays(now()) : null,
            'site_id' => (int) $site->site_id,
            'stage' => $stage,
            'category' => $category,
            'source' => $source,
            'blocks_stage' => $blocksStage,
            'blocks_downstream' => $blocksDownstream,
            'evidence' => $evidence,
        ];
    }

    private function overallStatus(Collection $assignments, Collection $activeAssignments, ?array $primaryIssue): string
    {
        if ($assignments->whereNotNull('assignment_id')->isEmpty()) {
            return 'not_started';
        }

        if ($activeAssignments->isEmpty()) {
            return 'dropped';
        }

        if ($activeAssignments->every(fn (object $row): bool => in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value], true))) {
            return 'done';
        }

        return match ($primaryIssue['type'] ?? null) {
            'construction_missing_wo',
            'site_missing_power',
            'survey_schedule_missing',
            'survey_evidence_missing',
            'pln_registration_incomplete',
            'pln_billing_incomplete',
            'pln_kwh_incomplete',
            'construction_data_incomplete',
            'construction_photos_missing',
            'bast_sim_missing',
            'bast_evidence_missing' => 'blocked',
            'stalled_assignment' => 'stalled',
            'revision_pending' => 'stalled',
            'ready_for_verification' => 'needs_review',
            'verified_not_reported' => 'ready_for_report',
            default => 'in_progress',
        };
    }

    /**
     * @param  Collection<int, object>  $assignments
     * @return array<string, mixed>|null
     */
    private function currentStage(Collection $assignments, ?array $primaryIssue): ?array
    {
        if ($primaryIssue !== null && filled($primaryIssue['blocks_stage'] ?? null)) {
            return [
                'key' => $primaryIssue['blocks_stage'],
                'label' => $this->stageLabel((string) $primaryIssue['blocks_stage']),
                'source' => 'primary_issue',
            ];
        }

        $active = $assignments->whereNotIn('status', [AssignmentStatus::Drop->value]);

        foreach ([ActivityType::Survey, ActivityType::PlnConnection, ActivityType::Construction, ActivityType::Bast] as $activity) {
            $row = $active->firstWhere('activity_type', $activity->value);

            if ($row === null || ! in_array($row->status, [AssignmentStatus::Verified->value, AssignmentStatus::Reported->value], true)) {
                return [
                    'key' => $activity->value,
                    'label' => $activity->label(),
                    'source' => 'workflow_sequence',
                ];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, object>  $assignments
     */
    private function flowExplanation(Collection $assignments, ?array $primaryIssue, ?array $currentStage, string $overallStatus): string
    {
        if ($primaryIssue !== null) {
            $stage = $currentStage['label'] ?? $primaryIssue['activity_type'] ?? 'site';
            $downstream = collect($primaryIssue['blocks_downstream'] ?? [])
                ->map(fn (string $stage): string => $this->stageLabel($stage))
                ->filter()
                ->join(', ');
            $suffix = filled($downstream) ? " It also blocks downstream work: {$downstream}." : '';

            return "{$stage}: {$primaryIssue['problem']}{$suffix}";
        }

        if ($overallStatus === 'done') {
            return 'All active workstreams for this site are verified or already reported.';
        }

        if ($overallStatus === 'not_started') {
            return 'No assignment has started yet, so the site has not entered the operational workflow.';
        }

        $stageLabel = $currentStage['label'] ?? 'Next workstream';

        return "{$stageLabel} is the next active stage. No critical structured blocker is currently detected.";
    }

    /**
     * @return list<string>
     */
    private function downstreamStages(?string $activityType): array
    {
        return match ($activityType) {
            ActivityType::Survey->value => [ActivityType::PlnConnection->value, ActivityType::Construction->value, ActivityType::Bast->value],
            ActivityType::PlnConnection->value => [ActivityType::Construction->value, ActivityType::Bast->value],
            ActivityType::Construction->value => [ActivityType::Bast->value],
            default => [],
        };
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            ActivityType::Survey->value, 'survey' => 'Survey',
            ActivityType::PlnConnection->value, 'pln' => 'PLN',
            ActivityType::Construction->value, 'construction' => 'Construction',
            ActivityType::Bast->value, 'bast' => 'BAST',
            'setup' => 'Setup',
            'scope' => 'Scope',
            default => $stage,
        };
    }

    /**
     * @param  Collection<int, string>  $mainContractorAdminOwners
     */
    private function mainContractorOwner(object $site, Collection $mainContractorAdminOwners): string
    {
        $adminOwner = $mainContractorAdminOwners->get((int) $site->main_contractor_id);

        if (filled($adminOwner)) {
            return $adminOwner;
        }

        if (filled($site->main_contractor_name)) {
            return "{$site->main_contractor_name} Admin";
        }

        return 'Main Contractor Admin';
    }
}
