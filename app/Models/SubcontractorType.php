<?php

namespace App\Models;

use App\Enums\ActivityType;
use Database\Factories\SubcontractorTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'activity_types'])]
class SubcontractorType extends Model
{
    /** @use HasFactory<SubcontractorTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_types' => 'array',
        ];
    }

    /**
     * Whether this sub-contractor type can handle the given activity.
     * Null activity_types = handles all (legacy / unconfigured).
     */
    public function handlesActivity(ActivityType $type): bool
    {
        if ($this->activity_types === null) {
            return true;
        }

        return in_array($type->value, $this->activity_types, true);
    }

    /**
     * @return HasMany<Subcontractor, $this>
     */
    public function subcontractors(): HasMany
    {
        return $this->hasMany(Subcontractor::class);
    }
}
