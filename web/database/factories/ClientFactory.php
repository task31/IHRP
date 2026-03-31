<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'billing_contact_name' => fake()->name(),
            'billing_address' => fake()->address(),
            'email' => fake()->companyEmail(),
            'smtp_email' => fake()->companyEmail(),
            'payment_terms' => 'Net 30',
            'total_budget' => null,
            'budget_alert_warning_sent' => false,
            'budget_alert_critical_sent' => false,
            'active' => true,
        ];
    }
}
