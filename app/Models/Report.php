<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Report extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'report_type',
        'exported_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exported_by' => 'int',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    /**
     * @return BelongsToMany<Assignment, $this>
     */
    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class, 'report_assignments');
    }
}
