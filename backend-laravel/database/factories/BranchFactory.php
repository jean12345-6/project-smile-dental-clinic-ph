<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'clinic_id'   => \App\Models\Clinic::factory(),
            'branch_name' => fake()->city() . ' Branch',
            'address'     => fake()->streetAddress() . ', Oriental Mindoro',
            'contact_no'  => '09' . fake()->numerify('#########'),
            'open_time'   => '08:00:00',
            'close_time'  => '17:00:00',
        ];
    }
}
