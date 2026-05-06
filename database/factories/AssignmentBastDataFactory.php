<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
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
            'plant_name' => fake()->company(),
            'installation_date' => fake()->date(),
            'commissioning_date' => fake()->date(),
        ])->afterCreating(function (AssignmentBastData $bast) {
            foreach (AssignmentBastData::REQUIRED_CHECKPOINTS as $key) {
                AssignmentBastPhoto::create([
                    'assignment_bast_data_id' => $bast->id,
                    'section' => 'required',
                    'checkpoint_key' => $key,
                    'photo_path' => 'bast/test/'.$key.'.jpg',
                ]);
            }
        });
    }
}
