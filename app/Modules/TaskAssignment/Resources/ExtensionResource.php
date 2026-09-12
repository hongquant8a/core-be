<?php

namespace App\Modules\TaskAssignment\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\TaskAssignment\Enums\TaskExtensionStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_assignment_item_id' => $this->task_assignment_item_id,
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->formatUserSummary($this->requestedBy)),
            'current_end_at' => $this->current_end_at?->format('H:i:s d/m/Y'),
            'requested_end_at' => $this->requested_end_at?->format('H:i:s d/m/Y'),
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => TaskExtensionStatusEnum::tryFrom($this->status)?->label(),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->formatUserSummary($this->reviewedBy)),
            'reviewed_at' => $this->reviewed_at?->format('H:i:s d/m/Y'),
            'review_note' => $this->review_note,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
