<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentConstructionData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentConstructionData>
 */
class AssignmentConstructionDataFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory()->construction(),
        ];
    }

    public function withPrerequisite(): self
    {
        return $this->state(fn () => [
            'cons_wo_number' => fake()->bothify('WO-####'),
            'project_status' => 'IN_PROGRESS',
            'setup_approval_date' => fake()->date(),
        ]);
    }

    public function complete(): self
    {
        return $this->withPrerequisite()->state(fn () => [
            'cons_actual_start_date' => fake()->date(),
            'cons_actual_done_date' => fake()->date(),
            'machine_serial_number' => fake()->bothify('SN-#####'),
            'catatan_progres' => fake()->sentence(),
        ]);
    }
}
