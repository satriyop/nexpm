<?php

namespace App\Models\Concerns;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToMainContractor
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereScopedToMainContractor(Builder $query): void
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if ($user === null || $user->role === Role::SuperAdmin) {
            return;
        }

        $query->where('main_contractor_id', $user->main_contractor_id);
    }
}
