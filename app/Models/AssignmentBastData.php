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
        'plant_name',
        'plant_address',
        'plant_coordinate',
        'gmaps_link',
        'charger_type',
        'sn_unit',
        'id_pln',
        'sim_provider',
        'installation_vendor',
        'pic_vendor_contact',
        'installation_date',
        'nomor_simcard',
        'commissioning_date',
        'customer',
        'go_live_date_pln_pass',
        'go_live_date_pln',
        'measurements',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'commissioning_date' => 'date',
            'go_live_date_pln_pass' => 'date',
            'go_live_date_pln' => 'date',
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
