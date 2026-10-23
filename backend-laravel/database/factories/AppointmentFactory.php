<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'patient_id'    => \App\Models\Patient::factory(),
            'dentist_id'    => \App\Models\Dentist::factory(),
            'branch_id'     => \App\Models\Branch::factory(),
            'service_id'    => \App\Models\Service::factory(),
            'start_time'    => $start = fake()->dateTimeBetween('now', '+3 weeks'),
            'end_time'      => (clone $start)->modify('+45 minutes'),
            'status'        => 'pending',
            'risk_flag'     => false,
            'booked_via'    => fake()->randomElement(['chatbot', 'portal', 'walk_in']),
            'check_in_time' => null,
            'follow_up_of'  => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'confirmed']);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'completed',
            'start_time' => $start = fake()->dateTimeBetween('-3 weeks', '-1 day'),
            'end_time'   => (clone $start)->modify('+45 minutes'),
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'no_show',
            'start_time' => $start = fake()->dateTimeBetween('-3 weeks', '-1 day'),
            'end_time'   => (clone $start)->modify('+45 minutes'),
        ]);
    }
}
