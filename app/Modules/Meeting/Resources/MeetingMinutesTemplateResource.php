<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingMinutesTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => (bool) $this->is_default,
            'status' => $this->status,
            'file_url' => $this->mediaFile ? '/storage/'.$this->mediaFile->id.'/'.$this->mediaFile->file_name : null,
            'file_name' => $this->mediaFile?->file_name,
            'created_by_name' => $this->creator?->name,
            'updated_by_name' => $this->editor?->name,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
