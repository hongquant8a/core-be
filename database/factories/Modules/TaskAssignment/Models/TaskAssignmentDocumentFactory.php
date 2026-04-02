<?php

namespace Database\Factories\Modules\TaskAssignment\Models;

use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TaskAssignment\Models\TaskAssignmentDocument>
 */
class TaskAssignmentDocumentFactory extends Factory
{
    protected $model = TaskAssignmentDocument::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(5),
            'summary' => $this->faker->paragraph(),
            'issue_date' => $this->faker->date(),
            'task_assignment_type_id' => null,
            'status' => 'draft',
            'issued_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
