<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use App\Modules\Beneficiary\Models\BeneficiaryTypeRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficiaryTypeRelationFactory extends Factory
{
    protected $model = BeneficiaryTypeRelation::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'beneficiary_id' => Beneficiary::factory(),
            'beneficiary_type_id' => BeneficiaryType::factory(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
