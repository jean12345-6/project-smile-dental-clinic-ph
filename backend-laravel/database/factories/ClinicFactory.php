<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        return [
            'clinic_name' => fake()->company() . ' Dental Clinic',
            'logo_path'   => null,
            'contact_no'  => '09' . fake()->numerify('#########'),
            'email'       => fake()->unique()->safeEmail(),
            'status'      => 'active',
        ];
    }
}
