<?php

namespace App\Modules\TaskAssignment\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_assignment_item_id' => $this->task_assignment_item_id,
            'author' => $this->whenLoaded('author', fn () => $this->formatUserSummary($this->author)),
            'author_role' => $this->author_role,
            'content' => $this->content,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
