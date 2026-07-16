<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\Household>
 */
class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    public function definition(): array
    {
        return [
            'household_code' => 'HGD-'.$this->faker->unique()->numerify('#####'),
            'head_name' => $this->faker->name(),
            'head_id_number' => $this->faker->numerify('#############'),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'member_count' => 0,
            'note' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
