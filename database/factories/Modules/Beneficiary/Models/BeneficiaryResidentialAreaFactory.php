<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Beneficiary\Models\BeneficiaryResidentialArea;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficiaryResidentialAreaFactory extends Factory
{
    protected $model = BeneficiaryResidentialArea::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'name' => 'Tổ dân phố '.$this->faker->unique()->numberBetween(1, 999),
            'note' => null,
            'sort_order' => 0,
            'status' => CatalogStatusEnum::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CatalogStatusEnum::Inactive->value]);
    }
}
