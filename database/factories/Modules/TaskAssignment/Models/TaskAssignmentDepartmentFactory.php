<?php

namespace Database\Factories\Modules\TaskAssignment\Models;

use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TaskAssignment\Models\TaskAssignmentDepartment>
 */
class TaskAssignmentDepartmentFactory extends Factory
{
    protected $model = TaskAssignmentDepartment::class;

    public function definition(): array
    {
        return [

            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'sort_order' => $this->faker->numberBetween(0, 100),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
