<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\MainContractor;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mainContractor = MainContractor::factory()->create();
        $client = Client::factory()->create();
        $client->mainContractors()->attach($mainContractor->id);
        $start = fake()->dateTimeBetween('-1 year', 'now');
        $end = fake()->dateTimeBetween($start, '+1 year');

        return [
            'main_contractor_id' => $mainContractor->id,
            'client_id' => $client->id,
            'name' => fake()->catchPhrase(),
            'start_date' => $start,
            'end_date' => $end,
            'budget' => fake()->randomFloat(2, 10000, 1000000),
        ];
    }
}
