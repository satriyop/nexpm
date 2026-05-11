<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'site_type_id',
        'machine_type_id',
        'site_code',
        'location_name',
        'address',
        'province',
        'city',
        'google_map_url',
        'latitude',
        'longitude',
        'power_kva',
        'bd_pic',
        'ss_wo_number',
        'cable_length_to_panel',
        'cable_length_panel_to_charger',
        'charging_station_count',
        'ssr_url',
        'nidi_slo_bpujl_url',
        'sik_url',
        'latest_remark',
        'invoice_submission_date',
        'dp_35_date',
        'invoice_60_submission_date',
        'payment_60_date',
        'invoice_5_submission_date',
        'payment_5_date',
        'invoice_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'cable_length_to_panel' => 'decimal:2',
            'cable_length_panel_to_charger' => 'decimal:2',
            'charging_station_count' => 'integer',
            'invoice_submission_date' => 'date',
            'dp_35_date' => 'date',
            'invoice_60_submission_date' => 'date',
            'payment_60_date' => 'date',
            'invoice_5_submission_date' => 'date',
            'payment_5_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<SiteType, $this>
     */
    public function siteType(): BelongsTo
    {
        return $this->belongsTo(SiteType::class);
    }

    /**
     * Alias for siteType to match frontend snake_case expectations.
     *
     * @return BelongsTo<SiteType, $this>
     */
    public function site_type(): BelongsTo
    {
        return $this->siteType();
    }

    /**
     * @return BelongsTo<MachineType, $this>
     */
    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class);
    }

    /**
     * @return HasMany<SitePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
