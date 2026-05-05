<?php

namespace Database\Factories;

use App\Models\MachineType;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'site_type_id' => SiteType::factory(),
            'machine_type_id' => MachineType::factory(),
            'site_code' => fake()->unique()->bothify('SITE-####'),
            'location_name' => fake()->company().' Site',
            'address' => fake()->address(),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'google_map_url' => fake()->url(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'bd_pic' => fake()->name(),
            'ss_wo_number' => fake()->bothify('WO-#####'),
            'cable_length_to_panel' => fake()->randomFloat(2, 1, 500),
            'charging_station_count' => fake()->numberBetween(1, 10),
            'ss_report_submission_date' => fake()->optional()->date(),
            'ssr_url' => fake()->optional()->url(),
            'bpujl_date_acquired' => fake()->optional()->date(),
            'nidi_slo_bpujl_url' => fake()->optional()->url(),
            'sik_url' => fake()->optional()->url(),
            'latest_remark' => fake()->optional()->sentence(),
            'invoice_submission_date' => fake()->optional()->date(),
            'dp_35_date' => fake()->optional()->date(),
            'invoice_60_submission_date' => fake()->optional()->date(),
            'payment_60_date' => fake()->optional()->date(),
            'invoice_5_submission_date' => fake()->optional()->date(),
            'payment_5_date' => fake()->optional()->date(),
            'invoice_url' => fake()->optional()->url(),
        ];
    }
}
