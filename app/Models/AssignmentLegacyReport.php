<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssignmentLegacyReport extends Model
{
    protected $fillable = [
        'assignment_id',
        'report_type',
        'file_path',
        'original_filename',
        'uploaded_by',
    ];

    protected $appends = ['download_url'];

    protected function downloadUrl(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->file_path));
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
