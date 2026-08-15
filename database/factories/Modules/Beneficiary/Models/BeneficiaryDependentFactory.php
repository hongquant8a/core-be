<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDependent;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficiaryDependentFactory extends Factory
{
    protected $model = BeneficiaryDependent::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'beneficiary_id' => Beneficiary::factory(),
            'full_name' => $this->faker->name(),
            'birth_date' => $this->faker->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(GenderEnum::values()),
            'id_number' => $this->faker->numerify('0############'),
            'phone' => $this->faker->numerify('09########'),
            'address' => $this->faker->streetAddress(),
            'latitude' => null,
            'longitude' => null,
            'note' => null,
            'relationship_id' => null,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
