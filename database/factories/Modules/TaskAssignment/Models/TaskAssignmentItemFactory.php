<?php

namespace Database\Factories\Modules\TaskAssignment\Models;

use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TaskAssignment\Models\TaskAssignmentItem>
 */
class TaskAssignmentItemFactory extends Factory
{
    protected $model = TaskAssignmentItem::class;

    public function definition(): array
    {
        return [
            'task_assignment_document_id' => TaskAssignmentDocument::factory(),
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'task_assignment_item_type_id' => null,
            'deadline_type' => 'no_deadline',
            'start_at' => null,
            'end_at' => null,
            'processing_status' => 'todo',
            'completion_percent' => 0,
            'priority' => 'medium',
            'completed_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
