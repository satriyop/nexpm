<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMainContractor;
use Database\Factories\SubcontractorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['main_contractor_id', 'name', 'phone', 'email', 'pic', 'code'])]
class Subcontractor extends Model
{
    /** @use HasFactory<SubcontractorFactory> */
    use HasFactory, ScopedToMainContractor;

    /**
     * @return BelongsTo<MainContractor, $this>
     */
    public function mainContractor(): BelongsTo
    {
        return $this->belongsTo(MainContractor::class);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
