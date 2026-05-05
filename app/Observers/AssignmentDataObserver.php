<?php

namespace App\Observers;

use App\Observers\Concerns\SyncsAssignmentStatus;
use Illuminate\Database\Eloquent\Model;

class AssignmentDataObserver
{
    use SyncsAssignmentStatus;

    public function saved(Model $model): void
    {
        $this->syncAssignmentStatus($model);
    }
}
