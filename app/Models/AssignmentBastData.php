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
     * Checkpoints required for the form to be considered complete.
     * Keys must exist in bastPhotos for isComplete() to return true.
     */
    public const REQUIRED_CHECKPOINTS = [
        'device_front_view_open',
        'device_front_view_close',
        'sim_kartu_perdana',
        'sim_installed_sim_card',
        'grounding_rod_connection',
        'grounding_cable_route',
        'grounding_test_ac_panel',
        'kwh_kwh_meter',
        'ac_front_view_open',
        'cable_spec',
    ];

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
        'commissioning_date',
        'customer',
        'measurements',
        'nomor_simcard',
        'go_live_date_pln_pass',
        'go_live_date_pln',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'commissioning_date' => 'date',
            'measurements' => 'array',
            'go_live_date_pln_pass' => 'date',
            'go_live_date_pln' => 'date',
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

    public function isComplete(): bool
    {
        if (! $this->plant_name || ! $this->installation_date || ! $this->commissioning_date) {
            return false;
        }

        $uploadedKeys = $this->bastPhotos()->pluck('checkpoint_key')->all();

        foreach (self::REQUIRED_CHECKPOINTS as $key) {
            if (! in_array($key, $uploadedKeys, true)) {
                return false;
            }
        }

        return true;
    }
}
