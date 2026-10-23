<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'user_id'         => \App\Models\User::factory(),
            'clinic_id'       => \App\Models\Clinic::factory(),
            'full_name'       => fake()->name(),
            'birthdate'       => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'address'         => fake()->streetAddress() . ', Oriental Mindoro',
            'contact_no'      => '09' . fake()->numerify('#########'),
            'email'           => fake()->unique()->safeEmail(),
            'medical_history' => fake()->optional(0.3)->sentence(),
            'no_show_count'   => 0,
        ];
    }

    /** A patient who has missed appointments before. */
    public function riskyAttendance(): static
    {
        return $this->state(fn (array $attributes) => [
            'no_show_count' => fake()->numberBetween(1, 4),
        ]);
    }
}
