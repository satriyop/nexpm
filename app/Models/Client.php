<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMainContractor;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['main_contractor_id', 'name', 'phone', 'email', 'pic'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, ScopedToMainContractor;

    /**
     * @return BelongsTo<MainContractor, $this>
     */
    public function mainContractor(): BelongsTo
    {
        return $this->belongsTo(MainContractor::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
