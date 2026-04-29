<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'citizen_id' => $this->citizen_id,
            'permanent_address' => $this->permanent_address,
            'temporary_address' => $this->temporary_address,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
