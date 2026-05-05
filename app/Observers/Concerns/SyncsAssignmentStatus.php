<?php

namespace App\Observers\Concerns;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use Illuminate\Database\Eloquent\Model;

trait SyncsAssignmentStatus
{
    /**
     * Re-evaluates the parent Assignment status based on the current data row.
     *
     * - PENDING/REVISION + complete data => COMPLETED
     * - COMPLETED + incomplete data      => PENDING (data was edited backwards)
     */
    protected function syncAssignmentStatus(Model $model): void
    {
        $assignment = Assignment::find($model->assignment_id);

        if ($assignment === null) {
            return;
        }

        $isComplete = $model->isComplete();

        if (
            in_array($assignment->status, [AssignmentStatus::Pending, AssignmentStatus::Revision], true)
            && $isComplete
        ) {
            $assignment->markCompleted();

            return;
        }

        if ($assignment->status === AssignmentStatus::Completed && ! $isComplete) {
            $assignment->status = AssignmentStatus::Pending;
            $assignment->save();
        }
    }
}
