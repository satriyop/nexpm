<?php

namespace App\Models;

use Database\Factories\AssignmentConstructionPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentConstructionPhoto extends Model
{
    /** @use HasFactory<AssignmentConstructionPhotoFactory> */
    use HasFactory;

    protected $table = 'assignment_construction_photos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_construction_data_id',
        'path',
    ];

    /**
     * @return BelongsTo<AssignmentConstructionData, $this>
     */
    public function constructionData(): BelongsTo
    {
        return $this->belongsTo(AssignmentConstructionData::class, 'assignment_construction_data_id');
    }
}
