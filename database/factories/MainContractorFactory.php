<?php

namespace Database\Factories;

use App\Models\MainContractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MainContractor>
 */
class MainContractorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'pic' => fake()->name(),
        ];
    }
}
