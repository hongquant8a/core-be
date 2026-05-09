<?php

namespace App\Modules\Meeting\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAttendeeResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            // Many-to-many groups (refactor 2026-05-08).
            'groups' => $this->whenLoaded('groups', fn () => $this->groups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
            ])->all(), []),
            'user_id' => $this->user_id,
            'name' => $this->name,
            'position_name' => $this->position_name,
            'department_name' => $this->department_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'note' => $this->note,
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
