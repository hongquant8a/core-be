<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'projector_image_url' => $this->projectorImage
                ? '/storage/'.$this->projectorImage->id.'/'.$this->projectorImage->file_name
                : null,
            'waiting_image_url' => $this->waitingImage
                ? '/storage/'.$this->waitingImage->id.'/'.$this->waitingImage->file_name
                : null,
            'chairperson_signature_url' => $this->chairpersonSignature
                ? '/storage/'.$this->chairpersonSignature->id.'/'.$this->chairpersonSignature->file_name
                : null,
            'qr_icon_url' => $this->qrIcon
                ? '/storage/'.$this->qrIcon->id.'/'.$this->qrIcon->file_name
                : null,
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
