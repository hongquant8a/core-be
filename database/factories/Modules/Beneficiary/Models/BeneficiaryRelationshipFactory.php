<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Beneficiary\Models\BeneficiaryRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficiaryRelationshipFactory extends Factory
{
    protected $model = BeneficiaryRelationship::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'name' => 'Quan hệ '.$this->faker->unique()->numberBetween(1, 999),
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
