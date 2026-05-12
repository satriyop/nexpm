<?php

namespace App\Models;

use App\Observers\AssignmentDataObserver;
use Database\Factories\AssignmentBastDataFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(AssignmentDataObserver::class)]
class AssignmentBastData extends Model
{
    /** @use HasFactory<AssignmentBastDataFactory> */
    use HasFactory;

    protected $table = 'assignment_bast_data';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_id',
        'sim_provider',
        'commissioning_date',
        'measurements',
        'nomor_simcard',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commissioning_date' => 'date',
            'measurements' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return HasMany<AssignmentBastPhoto, $this>
     */
    public function bastPhotos(): HasMany
    {
        return $this->hasMany(AssignmentBastPhoto::class);
    }
}
