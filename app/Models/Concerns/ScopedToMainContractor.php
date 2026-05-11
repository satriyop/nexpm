<?php

namespace App\Models\Concerns;

use App\Enums\Role;
use App\Models\Client;
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

        if ($user === null || $user->role === Role::SuperAdmin) {
            return;
        }

        // Use many-to-many for Client, direct column for others
        if ($this instanceof Client) {
            $query->whereHas('mainContractors', fn ($q) => $q->where('main_contractors.id', $user->main_contractor_id));
        } else {
            $query->where('main_contractor_id', $user->main_contractor_id);
        }
    }
}
