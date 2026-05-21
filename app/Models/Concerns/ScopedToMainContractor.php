<?php

namespace App\Models\Concerns;

use App\Models\Client;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToMainContractor
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereScopedToMainContractor(Builder $query): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null || $user->isGlobalAdmin()) {
            return;
        }

        // Use many-to-many for shared entities, direct column for owned entities.
        if ($this instanceof Client || $this instanceof Subcontractor) {
            $query->whereHas('mainContractors', fn ($q) => $q->where('main_contractors.id', $user->main_contractor_id));
        } else {
            $query->where('main_contractor_id', $user->main_contractor_id);
        }
    }
}
