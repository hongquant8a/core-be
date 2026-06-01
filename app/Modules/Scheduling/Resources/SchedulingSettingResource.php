<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchedulingSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'organization_id'       => $this->organization_id,
            'approval_enabled'      => (bool)$this->approval_enabled,
            'approval_module_types' => $this->approval_module_types,
            'default_channels'      => $this->default_channels,
            'working_sessions'      => $this->working_sessions,
        ];
    }
}
