<?php

namespace Database\Factories\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TaskAssignment\Models\TaskAssignmentEmployee>
 */
class TaskAssignmentEmployeeFactory extends Factory
{
    protected $model = TaskAssignmentEmployee::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'active',
            'note' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
