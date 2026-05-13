<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaloOaFollowerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,           // Zalo user_id — dùng để gán vào users.zalo_user_id
            'display_name' => $this->display_name,
            'user_alias' => $this->user_alias,
            'avatar' => $this->avatar,
            'last_synced_at' => $this->last_synced_at?->format('H:i:s d/m/Y'),
        ];
    }
}
