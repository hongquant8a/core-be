<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'user_type' => $this->user_type,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name ?? 'Guest',
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization?->name,
            'route' => $this->route,
            'method_type' => $this->method_type,
            'status_code' => $this->status_code,
            'ip_address' => $this->ip_address,
            'country' => $this->country,
            'user_agent' => $this->user_agent,
            'device_id' => $this->device_id,
            'request_data' => $this->request_data,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
