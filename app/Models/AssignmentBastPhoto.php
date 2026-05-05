<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentBastPhoto extends Model
{
    protected $fillable = [
        'assignment_bast_data_id',
        'section',
        'checkpoint_key',
        'photo_path',
    ];

    public function bastData(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AssignmentBastData::class, 'assignment_bast_data_id');
    }
}
