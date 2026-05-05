<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentSurveyData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSurveyData>
 */
class AssignmentSurveyDataFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory()->survey(),
        ];
    }

    public function complete(): self
    {
        return $this->state(fn () => [
            'surveyor_name' => fake()->name(),
            'pic_location_name' => fake()->name(),
            'pic_location_phone' => fake()->phoneNumber(),
            'charger_type' => 'EVCS',
            'ss_schedule_date' => fake()->date(),
            'cable_pulling_type' => 'overhead',
            'power_kva' => '50',
            'pln_network_type' => '3 phase',
            'parking_slot' => 'B1-' . fake()->numberBetween(1, 50),
            'photo_overall_site' => 'survey/overall.jpg',
            'photo_parking_evcs' => 'survey/parking.jpg',
            'photo_other_angle' => 'survey/other.jpg',
            'photo_pln_network' => 'survey/pln.jpg',
            'photo_satellite_gmaps' => 'survey/sat.jpg',
            'file_mockup_3d' => 'survey/mockup.pdf',
            'file_ba_survey' => 'survey/ba.pdf',
        ]);
    }
}
