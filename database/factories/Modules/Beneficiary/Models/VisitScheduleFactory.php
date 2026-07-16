<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use App\Modules\Beneficiary\Enums\VisitOccasionEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\VisitSchedule;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\VisitSchedule>
 */
class VisitScheduleFactory extends Factory
{
    protected $model = VisitSchedule::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Beneficiary())->getMorphClass(),
            'subject_id' => Beneficiary::factory(),
            'occasion' => $this->faker->randomElement(VisitOccasionEnum::values()),
            'scheduled_date' => now()->addDays(30),
            'status' => ScheduleStatusEnum::Pending->value,
            'assigned_to' => User::factory(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
