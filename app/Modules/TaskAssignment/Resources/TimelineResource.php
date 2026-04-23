<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->resource['type'],
            'id' => $this->resource['id'],
            'timestamp' => $this->resource['timestamp']?->format('H:i:s d/m/Y'),
            'actor' => $this->resource['actor'],
            'data' => $this->resource['data'],
        ];
    }
}
