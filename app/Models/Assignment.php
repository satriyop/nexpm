<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Notifications\AssignmentCompletedNotification;
use App\Notifications\AssignmentRevisionRequestedNotification;
use App\Notifications\AssignmentVerifiedNotification;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Notification;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'subcontractor_id',
        'activity_type',
        'status',
        'revision_comment',
        'verified_at',
        'verified_by',
        'reported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'status' => AssignmentStatus::class,
            'verified_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Subcontractor, $this>
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasOne<AssignmentSurveyData, $this>
     */
    public function surveyData(): HasOne
    {
        return $this->hasOne(AssignmentSurveyData::class);
    }

    /**
     * @return HasOne<AssignmentPlnData, $this>
     */
    public function plnData(): HasOne
    {
        return $this->hasOne(AssignmentPlnData::class);
    }

    /**
     * @return HasOne<AssignmentConstructionData, $this>
     */
    public function constructionData(): HasOne
    {
        return $this->hasOne(AssignmentConstructionData::class);
    }

    /**
     * Alias for constructionData to match frontend snake_case expectations.
     *
     * @return HasOne<AssignmentConstructionData, $this>
     */
    public function construction_data(): HasOne
    {
        return $this->constructionData();
    }

    /**
     * @return HasOne<AssignmentBastData, $this>
     */
    public function bastData(): HasOne
    {
        return $this->hasOne(AssignmentBastData::class);
    }

    /**
     * @return HasMany<AssignmentLegacyReport, $this>
     */
    public function legacyReports(): HasMany
    {
        return $this->hasMany(AssignmentLegacyReport::class);
    }

    /**
     * Construction assignment is locked for sub-con until the
     * admin fills the WO number prerequisite.
     */
    public function isLocked(): bool
    {
        if ($this->activity_type !== ActivityType::Construction) {
            return false;
        }

        $constructionData = $this->constructionData;

        if ($constructionData === null) {
            return true;
        }

        return blank($constructionData->cons_wo_number);
    }

    /**
     * Whether the activity-specific data has all required sub-con fields filled.
     */
    public function isCompletedBySubcon(): bool
    {
        return match ($this->activity_type) {
            ActivityType::Survey => $this->surveyData?->isComplete() ?? false,
            ActivityType::PlnConnection => $this->plnData?->isComplete() ?? false,
            ActivityType::Construction => $this->constructionData?->isComplete() ?? false,
            ActivityType::Bast => $this->status === AssignmentStatus::Submitted,
            default => false,
        };
    }

    public function markSubmitted(): void
    {
        $this->status = AssignmentStatus::Submitted;
        $this->save();

        $this->load('site.project');
        $mainContractorId = $this->site->project->main_contractor_id;
        $admins = User::query()
            ->where('main_contractor_id', $mainContractorId)
            ->whereIn('role', [Role::SuperAdmin, Role::Admin])
            ->get();
        Notification::send($admins, new AssignmentCompletedNotification($this));
    }

    public function markVerified(User $verifier): void
    {
        $this->status = AssignmentStatus::Verified;
        $this->verified_by = $verifier->id;
        $this->verified_at = now();
        $this->save();

        $subconUsers = User::query()->where('subcontractor_id', $this->subcontractor_id)->get();
        Notification::send($subconUsers, new AssignmentVerifiedNotification($this));
    }

    public function sendToRevision(string $comment): void
    {
        $this->status = AssignmentStatus::Revision;
        $this->revision_comment = $comment;
        $this->save();

        $subconUsers = User::query()->where('subcontractor_id', $this->subcontractor_id)->get();
        Notification::send($subconUsers, new AssignmentRevisionRequestedNotification($this));
    }

    /**
     * @return BelongsToMany<Report, $this>
     */
    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'report_assignments');
    }

    public function markReported(): void
    {
        $this->status = AssignmentStatus::Reported;
        $this->reported_at = now();
        $this->save();
    }

    public function markDropped(): void
    {
        $this->status = AssignmentStatus::Drop;
        $this->save();
    }
}
