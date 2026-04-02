<?php

namespace Database\Factories\Modules\TaskAssignment\Models;

use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TaskAssignment\Models\TaskAssignmentType>
 */
class TaskAssignmentTypeFactory extends Factory
{
    protected $model = TaskAssignmentType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
