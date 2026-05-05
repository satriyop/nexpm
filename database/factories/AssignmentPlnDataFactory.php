<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentPlnData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentPlnData>
 */
class AssignmentPlnDataFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory()->plnConnection(),
        ];
    }

    public function complete(): self
    {
        return $this->state(fn () => [
            'pln_status' => 'DONE KWH',
            'nidi_slo_date_acquired' => fake()->date(),
            'type_rate' => 'B-3',
            'file_slo' => 'pln/slo.pdf',
            'file_nidi' => 'pln/nidi.pdf',
            'file_reg' => 'pln/reg.pdf',
            'kwh_meter_installation_date' => fake()->date(),
            'id_pelanggan' => fake()->numerify('############'),
            'catatan_progres' => fake()->sentence(),
        ]);
    }
}
