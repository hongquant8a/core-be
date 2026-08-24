<?php

namespace App\Modules\TaskAssignment\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'employees_count' => $this->whenCounted('employeeMemberships'),
            'employees' => $this->whenLoaded('employeeMemberships', fn () => $this->employeeMemberships
                ->map(fn ($m) => [
                    'employee_id' => $m->task_assignment_employee_id,
                    'user_id' => $m->employee?->user_id,
                    'name' => $m->employee?->user?->name,
                    'status' => $m->employee?->status,
                    'is_representative' => (bool) $m->is_representative,
                ])
                ->values(),
                []
            ),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
