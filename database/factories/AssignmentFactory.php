<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Site;
use App\Models\Subcontractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'subcontractor_id' => Subcontractor::factory(),
            'activity_type' => ActivityType::Survey,
            'status' => AssignmentStatus::Pending,
            'revision_comment' => null,
            'verified_at' => null,
            'verified_by' => null,
            'reported_at' => null,
        ];
    }

    public function survey(): self
    {
        return $this->state(fn () => ['activity_type' => ActivityType::Survey]);
    }

    public function plnConnection(): self
    {
        return $this->state(fn () => ['activity_type' => ActivityType::PlnConnection]);
    }

    public function construction(): self
    {
        return $this->state(fn () => ['activity_type' => ActivityType::Construction]);
    }

    public function bast(): self
    {
        return $this->state(fn () => ['activity_type' => ActivityType::Bast]);
    }
}
