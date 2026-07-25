<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryClassificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => BeneficiaryTypeEnum::tryFrom($this->type)?->label() ?? $this->type,
            'decision_no' => $this->decision_no,
            'decision_date' => $this->decision_date?->format('d/m/Y'),
            'issued_by' => $this->issued_by,
            'is_primary' => (bool) $this->is_primary,
            'decision_files' => $this->whenLoaded('media', fn () => $this->getMedia('decision_documents')->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $media->getFullUrl(),
                'size' => $media->size,
            ])),
        ];
    }
}
