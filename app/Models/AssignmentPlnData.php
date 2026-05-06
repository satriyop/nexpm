<?php

namespace App\Models;

use App\Observers\AssignmentDataObserver;
use Database\Factories\AssignmentPlnDataFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AssignmentDataObserver::class)]
class AssignmentPlnData extends Model
{
    /** @use HasFactory<AssignmentPlnDataFactory> */
    use HasFactory;

    protected $table = 'assignment_pln_data';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_id',
        'pln_status',
        'nidi_slo_date_acquired',
        'type_rate',
        'file_slo',
        'file_nidi',
        'file_reg',
        'kwh_meter_installation_date',
        'id_pelanggan',
        'catatan_progres',
        'email_bpujl_req_date',
        'bpujl_acquired_date',
        'foto_kwh',
    ];

    /**
     * @var list<string>
     */
    public const REQUIRED_SUBCON_FIELDS = [
        'file_slo',
        'file_nidi',
        'file_reg',
        'email_bpujl_req_date',
        'bpujl_acquired_date',
        'type_rate',
        'id_pelanggan',
        'kwh_meter_installation_date',
        'foto_kwh',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nidi_slo_date_acquired' => 'date',
            'kwh_meter_installation_date' => 'date',
            'email_bpujl_req_date' => 'date',
            'bpujl_acquired_date' => 'date',
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
