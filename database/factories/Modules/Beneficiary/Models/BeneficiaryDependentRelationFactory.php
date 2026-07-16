<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\DependentRelationStatusEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDependentRelation;
use App\Modules\Beneficiary\Models\Dependent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\BeneficiaryDependentRelation>
 */
class BeneficiaryDependentRelationFactory extends Factory
{
    protected $model = BeneficiaryDependentRelation::class;

    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'dependent_id' => Dependent::factory(),
            'relationship_type' => $this->faker->randomElement(DependentRelationshipEnum::values()),
            'eligible_from' => now()->subYear(),
            'status' => DependentRelationStatusEnum::Active->value,
        ];
    }
}
