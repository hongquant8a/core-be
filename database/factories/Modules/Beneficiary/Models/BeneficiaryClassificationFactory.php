<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\BeneficiaryClassification>
 */
class BeneficiaryClassificationFactory extends Factory
{
    protected $model = BeneficiaryClassification::class;

    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'type' => $this->faker->randomElement(BeneficiaryTypeEnum::values()),
            'decision_no' => $this->faker->bothify('QD-####/####'),
            'decision_date' => $this->faker->dateTimeBetween('-10 years', 'now'),
            'issued_by' => 'Sở Lao động - Thương binh và Xã hội TP Đà Nẵng',
            'is_primary' => true,
        ];
    }
}
