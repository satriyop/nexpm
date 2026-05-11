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
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'pic' => fake()->name(),
            'code' => fake()->unique()->bothify('SUB-####'),
        ];
    }

    public function forMainContractor(MainContractor $mainContractor): static
    {
        return $this->afterCreating(function (Subcontractor $subcontractor) use ($mainContractor): void {
            $subcontractor->mainContractors()->syncWithoutDetaching($mainContractor->id);
        });
    }
}
