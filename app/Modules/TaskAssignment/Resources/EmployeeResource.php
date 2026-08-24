<?php

namespace App\Modules\TaskAssignment\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->formatUserSummary($this->user), null),
            'status' => $this->status,
            'note' => $this->note,
            'departments' => $this->whenLoaded('departmentMemberships', fn () => $this->departmentMemberships
                ->map(fn ($m) => [
                    'id' => $m->task_assignment_department_id,
                    'name' => $m->department?->name,

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
