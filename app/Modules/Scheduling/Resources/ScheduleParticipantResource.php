<?php

namespace App\Modules\Scheduling\Resources;

use App\Modules\Core\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'schedule_id'     => $this->schedule_id,
            'organization_id' => $this->organization_id,
            'user_id'         => $this->user_id,
            'user'            => new UserResource($this->whenLoaded('user')),
            'display_name'    => $this->display_name,
            'position_name'   => $this->position_name,
            'is_external'     => $this->is_external,
            'sort_order'      => $this->sort_order,
        ];
    }
}
