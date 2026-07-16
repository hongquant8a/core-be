<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\SubsidyGrant;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\SubsidyGrant>
 */
class SubsidyGrantFactory extends Factory
{
    protected $model = SubsidyGrant::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Beneficiary())->getMorphClass(),
            'subject_id' => Beneficiary::factory(),
            'beneficiary_subsidy_policy_id' => SubsidyPolicy::factory(),
            'amount' => $this->faker->numberBetween(1_500_000, 6_000_000),
            'granted_from' => now()->subMonths(6),
            'status' => SubsidyStatusEnum::Active->value,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
