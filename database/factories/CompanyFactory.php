<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'slug' => fake()->slug(),
            'description' => fake()->sentence(),
            'specialty' => fake()->jobTitle(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'website' => fake()->url(),
            'total_clients' => fake()->numberBetween(10, 500),
            'total_specialists' => fake()->numberBetween(1, 10),
            'years_experience' => fake()->numberBetween(1, 20),
            'satisfaction_rate' => fake()->randomFloat(2, 80, 100),
            'is_active' => true,
            'settings' => [
                'work_start_time' => '09:00',
                'work_end_time' => '18:00',
                'appointment_interval' => 30,
                'appointment_days_ahead' => 14,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'email_notifications' => true,
                'require_confirmation' => false,
                'holidays' => [],
                'break_times' => [],
                'max_appointments_per_slot' => 1
            ]
        ];
    }
}
