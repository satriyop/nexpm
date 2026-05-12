<?php

namespace App\Observers\Concerns;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use Illuminate\Database\Eloquent\Model;

trait SyncsAssignmentStatus
{
    protected function syncAssignmentStatus(Model $model): void
    {
        $assignment = Assignment::find($model->assignment_id);

        if ($assignment === null) {
            return;
        }

        // Never auto-overwrite admin-set statuses.
        if (in_array($assignment->status, AssignmentStatus::adminLocked(), true)) {
            return;
        }

        $target = match ($assignment->activity_type) {
            ActivityType::Survey => $this->computeSurveyStatus($model),
            ActivityType::PlnConnection => $this->computePlnStatus($model),
            ActivityType::Construction => $this->computeConstructionStatus($model),
            ActivityType::Bast => AssignmentStatus::Pending,
        };

        if (
            $target !== $assignment->status
            && $this->isHigher($target, $assignment->status, $assignment->activity_type)
        ) {
            $assignment->status = $target;
            $assignment->saveQuietly();
        }
    }

    private function computeSurveyStatus(Model $data): AssignmentStatus
    {
        if (! $data instanceof AssignmentSurveyData) {
            return AssignmentStatus::Pending;
        }

        if ($data->isComplete()) {
            return AssignmentStatus::Document;
        }

        if (! empty($data->ss_schedule_date)) {
            return AssignmentStatus::Survey;
        }

        return AssignmentStatus::Pending;
    }

    private function computeConstructionStatus(Model $data): AssignmentStatus
    {
        if (! $data instanceof AssignmentConstructionData) {
            return AssignmentStatus::Pending;
        }

        if (! empty($data->go_live_date_pln)) {
            return AssignmentStatus::Live;
        }

        if (! empty($data->cons_actual_done_date)) {
            return AssignmentStatus::Done;
        }

        if (! empty($data->machine_serial_number) && ! empty($data->foto_machine_sn)) {
            return AssignmentStatus::MachineOnsite;
        }

        if (! empty($data->cons_actual_start_date)) {
            return AssignmentStatus::Construction;
        }

        return AssignmentStatus::Pending;
    }

    private function computePlnStatus(Model $data): AssignmentStatus
    {
        if (! $data instanceof AssignmentPlnData) {
            return AssignmentStatus::Pending;
        }

        if (
            ! empty($data->type_rate)
            && ! empty($data->id_pelanggan)
            && ! empty($data->kwh_meter_installation_date)
            && ! empty($data->foto_kwh)
        ) {
            return AssignmentStatus::KwhDone;
        }

        if (! empty($data->bpujl_acquired_date)) {
            return AssignmentStatus::Connection;
        }

        if (! empty($data->email_bpujl_req_date)) {
            return AssignmentStatus::Billing;
        }

        if (! empty($data->file_slo) && ! empty($data->file_nidi) && ! empty($data->file_reg)) {
            return AssignmentStatus::Registration;
        }

        return AssignmentStatus::Pending;
    }

    /**
     * Returns true when $candidate is a higher status than $current for the given activity.
     * Ensures status only ever advances forward.
     *
     * @param  list<AssignmentStatus>  $order
     */
    private function isHigher(AssignmentStatus $candidate, AssignmentStatus $current, ActivityType $activity): bool
    {
        $order = match ($activity) {
            ActivityType::Survey => [
                AssignmentStatus::Pending,
                AssignmentStatus::Survey,
                AssignmentStatus::Document,
            ],
            ActivityType::Construction => [
                AssignmentStatus::Pending,
                AssignmentStatus::Construction,
                AssignmentStatus::MachineOnsite,
                AssignmentStatus::Done,
                AssignmentStatus::Live,
            ],
            ActivityType::PlnConnection => [
                AssignmentStatus::Pending,
                AssignmentStatus::Registration,
                AssignmentStatus::Billing,
                AssignmentStatus::Connection,
                AssignmentStatus::KwhDone,
            ],
            ActivityType::Bast => [
                AssignmentStatus::Pending,
                AssignmentStatus::Submitted,
            ],
        };

        $candidatePos = array_search($candidate, $order, true);
        $currentPos = array_search($current, $order, true);

        if ($candidatePos === false || $currentPos === false) {
            return false;
        }

        return $candidatePos > $currentPos;
    }
}
