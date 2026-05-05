<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentBastData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentBastData>
 */
class AssignmentBastDataFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory()->bast(),
        ];
    }

    public function complete(): self
    {
        return $this->state(fn () => [
            'nomor_simcard' => fake()->numerify('SIM##########'),
            'go_live_date_pln_pass' => fake()->date(),
            'go_live_date_pln' => fake()->date(),
            'catatan_progres' => fake()->sentence(),
        ]);
    }
}
