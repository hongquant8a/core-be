<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Dependent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\Dependent>
 */
class DependentFactory extends Factory
{
    protected $model = Dependent::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'date_of_birth' => $this->faker->dateTimeBetween('-80 years', '-1 years'),
            'gender' => $this->faker->randomElement(GenderEnum::values()),
            'id_number' => $this->faker->unique()->numerify('#############'),
            'phone' => $this->faker->phoneNumber(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
