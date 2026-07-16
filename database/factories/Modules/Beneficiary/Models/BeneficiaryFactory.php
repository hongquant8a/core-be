<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    protected $model = Beneficiary::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'date_of_birth' => $this->faker->dateTimeBetween('-90 years', '-30 years'),
            'gender' => $this->faker->randomElement(GenderEnum::values()),
            'id_number' => $this->faker->unique()->numerify('#############'),
            'status' => BeneficiaryStatusEnum::Active->value,
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
