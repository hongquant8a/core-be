<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingSeatMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'layout_type' => $this->layout_type,
            'config' => $this->config,
            'canvas' => $this->canvas,
            'seats' => MeetingSeatResource::collection($this->whenLoaded('seats')),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
