<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'clinic_id'    => \App\Models\Clinic::factory(),
            'service_name' => fake()->randomElement([
                'Consultation', 'Oral Prophylaxis', 'Tooth Extraction',
                'Composite Filling', 'Root Canal Treatment', 'Dental Crown',
            ]),
            'duration_min' => fake()->randomElement([30, 45, 60, 90]),
            'price'        => fake()->randomElement([500, 800, 1200, 1500, 6000, 8000]),
            'is_active'    => true,
        ];
    }
}
