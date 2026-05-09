<?php

namespace App\Modules\Meeting\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address ?? null,
            'google_maps_url' => $this->google_maps_url ?? null,
            'status' => $this->status,
            // Chỉ áp dụng cho MeetingAttendeeGroup khi withCount('attendees') được gọi.
            // Resource dùng chung cho nhiều catalog → field trả khi loaded mới có.
            'attendees_count' => $this->when(isset($this->attendees_count), (int) $this->attendees_count),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
