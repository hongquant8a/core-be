<?php

namespace App\Modules\Scheduling\Resources;

use App\Modules\Core\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchedulingEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'user_id'         => $this->user_id,
            'user'            => new UserResource($this->whenLoaded('user')),
            'name'            => $this->name,
            'position_name'   => $this->position_name,
            'department'      => $this->department,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'priority_weight' => $this->priority_weight,
            'status'          => (bool)$this->status,
            'sort_order'      => $this->sort_order,
            'groups'          => SchedulingEmployeeGroupResource::collection($this->whenLoaded('groups')),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
