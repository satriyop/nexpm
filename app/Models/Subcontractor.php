<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMainContractor;
use Database\Factories\SubcontractorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'phone', 'email', 'pic', 'code'])]
class Subcontractor extends Model
{
    /** @use HasFactory<SubcontractorFactory> */
    use HasFactory, ScopedToMainContractor;

    /**
     * @return BelongsToMany<MainContractor, $this>
     */
    public function mainContractors(): BelongsToMany
    {
        return $this->belongsToMany(MainContractor::class, 'main_contractor_subcontractor');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
