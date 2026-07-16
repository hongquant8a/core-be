<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\SubsidyPolicy>
 */
class SubsidyPolicyFactory extends Factory
{
    protected $model = SubsidyPolicy::class;

    public function definition(): array
    {
        return [
            'beneficiary_type' => $this->faker->randomElement(BeneficiaryTypeEnum::values()),
            'amount' => $this->faker->numberBetween(1_500_000, 6_000_000),
            'unit' => 'VND/tháng',
            'legal_basis' => 'Nghị định 75/2021/NĐ-CP',
            'effective_from' => now()->subYear(),
            'effective_to' => null,
        ];
    }
}
