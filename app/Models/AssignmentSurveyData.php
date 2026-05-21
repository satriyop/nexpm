<?php

namespace App\Models;

use App\Observers\AssignmentDataObserver;
use Database\Factories\AssignmentSurveyDataFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AssignmentDataObserver::class)]
class AssignmentSurveyData extends Model
{
    /** @use HasFactory<AssignmentSurveyDataFactory> */
    use HasFactory;

    protected $table = 'assignment_survey_data';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_id',
        'ss_wo_number',
        'surveyor_name',
        'pic_location_name',
        'pic_location_phone',
        'charger_type',
        'ss_schedule_date',
        'cable_pulling_type',
        'power_kva',
        'pln_network_type',
        'additional_info',
        'photo_overall_site',
        'photo_parking_evcs',
        'photo_access_route',
        'photo_pln_network',
        'photo_satellite_gmaps',
        'file_mockup_3d',
        'file_site_plan',
        'file_ba_survey',
        'file_boq',
        'parking_slot',
        'ss_report_submission_date',
    ];

    /**
     * Required sub-con field list for auto-completion.
     *
     * @var list<string>
     */
    public const REQUIRED_SUBCON_FIELDS = [
        'surveyor_name',
        'pic_location_name',
        'pic_location_phone',
        'charger_type',
        'ss_schedule_date',
        'cable_pulling_type',
        'power_kva',
        'pln_network_type',
        'parking_slot',
        'photo_overall_site',
        'photo_parking_evcs',
        'photo_access_route',
        'photo_pln_network',
        'photo_satellite_gmaps',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ss_schedule_date' => 'date',
            'ss_report_submission_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function isComplete(): bool
    {
        foreach (self::REQUIRED_SUBCON_FIELDS as $field) {
            $value = $this->{$field};

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }
}
