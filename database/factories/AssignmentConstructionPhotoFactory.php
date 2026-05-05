<?php

namespace Database\Factories;

use App\Models\AssignmentConstructionData;
use App\Models\AssignmentConstructionPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentConstructionPhoto>
 */
class AssignmentConstructionPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_construction_data_id' => AssignmentConstructionData::factory(),
            'path' => 'construction/'.fake()->uuid().'.jpg',
        ];
    }
}
