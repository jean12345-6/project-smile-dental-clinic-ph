<?php

namespace Database\Factories;

use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
{
    protected $model = Treatment::class;

    public function definition(): array
    {
        return [
            'appointment_id' => \App\Models\Appointment::factory(),
            'patient_id'     => \App\Models\Patient::factory(),
            'dentist_id'     => \App\Models\Dentist::factory(),
            'tooth_no'       => (string) fake()->numberBetween(11, 48),
            'procedure_name' => fake()->randomElement([
                'Oral Prophylaxis', 'Composite Filling', 'Tooth Extraction',
            ]),
            'notes'          => fake()->optional(0.6)->sentence(),
            'treatment_date' => fake()->dateTimeBetween('-3 weeks', 'now')->format('Y-m-d'),
            'cost'           => fake()->randomElement([800, 1200, 1500, 8000]),
        ];
    }
}
