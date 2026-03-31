<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Consultant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultant>
 */
class ConsultantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'pay_rate' => '50.0000',
            'bill_rate' => '65.0000',
            'state' => 'CA',
            'industry_type' => 'it',
            'client_id' => Client::factory(),
            'project_start_date' => now()->toDateString(),
            'project_end_date' => now()->addDays(30)->toDateString(),
            'w9_on_file' => false,
            'w9_file_path' => null,
            'contract_on_file' => false,
            'contract_file_path' => null,
            'active' => true,
        ];
    }
}
