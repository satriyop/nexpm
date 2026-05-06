<?php

namespace App\Models;

use App\Observers\AssignmentDataObserver;
use Database\Factories\AssignmentConstructionDataFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(AssignmentDataObserver::class)]
class AssignmentConstructionData extends Model
{
    /** @use HasFactory<AssignmentConstructionDataFactory> */
    use HasFactory;

    protected $table = 'assignment_construction_data';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_id',
        'cons_wo_number',
        'project_status',
        'setup_approval_date',
        'cons_actual_start_date',
        'cons_actual_done_date',
        'machine_serial_number',
        'foto_machine_sn',
        'catatan_progres',
        'go_live_date_pln',
        'go_live_date_pln_pass',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'setup_approval_date' => 'date',
            'cons_actual_start_date' => 'date',
            'cons_actual_done_date' => 'date',
            'go_live_date_pln' => 'date',
            'go_live_date_pln_pass' => 'date',
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
     * @return HasMany<AssignmentConstructionPhoto, $this>
     */
    public function constructionPhotos(): HasMany
    {
        return $this->hasMany(AssignmentConstructionPhoto::class);
    }

    public function isPrerequisiteMet(): bool
    {
        return ! ($this->cons_wo_number === null || $this->cons_wo_number === '');
    }

    public function isComplete(): bool
    {
        if (! $this->isPrerequisiteMet()) {
            return false;
        }

        if ($this->cons_actual_start_date === null) {
            return false;
        }

        if ($this->cons_actual_done_date === null) {
            return false;
        }

        if ($this->machine_serial_number === null || $this->machine_serial_number === '') {
            return false;
        }

        if ($this->foto_machine_sn === null || $this->foto_machine_sn === '') {
            return false;
        }

        if ($this->catatan_progres === null || $this->catatan_progres === '') {
            return false;
        }

        return $this->constructionPhotos()->count() > 0;
    }
}
