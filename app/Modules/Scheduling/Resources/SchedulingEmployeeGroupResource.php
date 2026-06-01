<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchedulingEmployeeGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'name'            => $this->name,
            'description'     => $this->description,
            'status'          => (bool)$this->status,
            'sort_order'      => $this->sort_order,
            'members_count'   => $this->whenCounted('members'),
            'members'         => SchedulingEmployeeResource::collection($this->whenLoaded('members')),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
