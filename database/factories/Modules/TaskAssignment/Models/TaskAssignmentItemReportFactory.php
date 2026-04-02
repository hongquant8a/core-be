<?php

namespace Database\Factories\Modules\TaskAssignment\Models;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\TaskAssignment\Models\TaskAssignmentItemReport>
 */
class TaskAssignmentItemReportFactory extends Factory
{
    protected $model = TaskAssignmentItemReport::class;

    public function definition(): array
    {
        return [
            'task_assignment_item_id' => TaskAssignmentItem::factory(),
            'reporter_user_id' => null,
            'completed_at' => null,
            'report_document_number' => $this->faker->bothify('BC-##/2026'),
            'report_document_excerpt' => $this->faker->sentence(),
            'report_document_content' => $this->faker->paragraphs(2, true),
        ];
    }
}
