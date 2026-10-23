<?php

namespace Database\Factories;

use App\Models\Dentist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dentist>
 */
class DentistFactory extends Factory
{
    protected $model = Dentist::class;

    public function definition(): array
    {
        return [
            'user_id'        => \App\Models\User::factory(),
            'full_name'      => 'Dr. ' . fake()->name(),
            'specialization' => fake()->randomElement([
                'General Dentistry', 'Orthodontics', 'Endodontics',
                'Pediatric Dentistry', 'Periodontics',
            ]),
            'license_no'     => 'PRC-' . fake()->numerify('#######'),
        ];
    }
}
