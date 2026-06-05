<?php

namespace App\Models;

use App\Observers\AssignmentDataObserver;
use Database\Factories\AssignmentPlnDataFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'file_pk',
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
        'file_pk',
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
     * @return Attribute<?string, ?string>
     */
    protected function idPelanggan(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?string => self::normalizeIdPelanggan($value),
            set: fn (mixed $value): ?string => self::normalizeIdPelanggan($value),
        );
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

    private static function normalizeIdPelanggan(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^([+-]?)(\d+)(?:[,.](\d+))?[eE]([+-]?\d+)$/', $value, $matches)) {
            return $value;
        }

        $sign = $matches[1] === '-' ? '-' : '';
        $whole = $matches[2];
        $fraction = $matches[3] ?? '';
        $exponent = (int) $matches[4];
        $digits = ltrim($whole.$fraction, '0');

        if ($digits === '') {
            return '0';
        }

        $shift = $exponent - strlen($fraction);

        if ($shift >= 0) {
            return $sign.$digits.str_repeat('0', $shift);
        }

        $position = strlen($digits) + $shift;

        if ($position <= 0) {
            $normalized = '0.'.str_repeat('0', abs($position)).$digits;
        } else {
            $normalized = substr($digits, 0, $position).'.'.substr($digits, $position);
        }

        return $sign.rtrim(rtrim($normalized, '0'), '.');
    }
}
