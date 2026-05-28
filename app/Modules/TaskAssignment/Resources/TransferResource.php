<?php

namespace App\Modules\TaskAssignment\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_assignment_item_id' => $this->task_assignment_item_id,
            'from_user' => $this->whenLoaded('fromUser', fn () => $this->formatUserSummary($this->fromUser)),
            'to_user' => $this->whenLoaded('toUser', fn () => $this->formatUserSummary($this->toUser)),
            'transferred_by' => $this->whenLoaded('transferredBy', fn () => $this->formatUserSummary($this->transferredBy)),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,

                'name' => $this->department?->name,
            ]),
            'transferred_at' => $this->transferred_at?->format('H:i:s d/m/Y'),
            'note' => $this->note,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
