<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMainContractor;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['main_contractor_id', 'client_id', 'name', 'start_date', 'end_date', 'budget'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, ScopedToMainContractor;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MainContractor, $this>
     */
    public function mainContractor(): BelongsTo
    {
        return $this->belongsTo(MainContractor::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

}
