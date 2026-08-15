<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficiaryFactory extends Factory
{
    protected $model = Beneficiary::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'full_name' => $this->faker->name(),
            'birth_date' => $this->faker->dateTimeBetween('-90 years', '-40 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(GenderEnum::values()),
            'id_number' => $this->faker->unique()->numerify('0############'),
            'phone' => $this->faker->numerify('09########'),
            'address' => $this->faker->streetAddress(),
            // Quanh Đà Nẵng — để dữ liệu mẫu chấm được lên bản đồ.
            'latitude' => $this->faker->randomFloat(7, 15.95, 16.15),
            'longitude' => $this->faker->randomFloat(7, 108.10, 108.30),
            'note' => null,
        ];
    }
}
