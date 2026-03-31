<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Consultant;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placement>
 */
class PlacementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultant_id' => Consultant::factory(),
            'client_id' => Client::factory(),
            'placed_by' => User::factory(),
            'job_title' => fake()->jobTitle(),
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'pay_rate' => '45.0000',
            'bill_rate' => '60.0000',
            'status' => 'active',
            'notes' => null,
        ];
    }
}
