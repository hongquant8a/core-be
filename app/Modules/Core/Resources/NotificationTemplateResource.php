<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'organization_id'  => $this->organization_id,
            'module_key'       => $this->module_key,
            'event_key'        => $this->event_key,
            'channel'          => $this->channel,
            'template_id'      => $this->template_id,
            'variable_mapping' => $this->variable_mapping,
            'is_default'       => $this->is_default,
            'status'           => $this->status,
            'created_by'       => $this->created_by,
            'updated_by'       => $this->updated_by,
            'created_at'       => $this->created_at->format('H:i:s d/m/Y'),
            'updated_at'       => $this->updated_at->format('H:i:s d/m/Y'),
        ];
    }
}
