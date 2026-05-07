<?php

namespace Database\Factories;

use App\Models\MainContractor;
use App\Models\Subcontractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subcontractor>
 */
class SubcontractorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'main_contractor_id' => MainContractor::factory(),
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'pic' => fake()->name(),
            'code' => fake()->unique()->bothify('SUB-####'),
        ];
    }
}
