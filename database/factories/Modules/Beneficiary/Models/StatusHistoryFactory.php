<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\StatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\StatusHistory>
 */
class StatusHistoryFactory extends Factory
{
    protected $model = StatusHistory::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Beneficiary())->getMorphClass(),
            'subject_id' => Beneficiary::factory(),
            'old_status' => 'pending',
            'new_status' => 'active',
            'reason' => null,
            'changed_by' => null,
            'changed_at' => now(),
        ];
    }
}
