<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\DependentRelationStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DependentRelationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_id' => $this->beneficiary_id,
            'beneficiary' => $this->whenLoaded('beneficiary', fn () => $this->beneficiary ? [
                'id' => $this->beneficiary->id,
                'full_name' => $this->beneficiary->full_name,
            ] : null),
            'relationship_type' => $this->relationship_type,
            'relationship_type_label' => DependentRelationshipEnum::tryFrom($this->relationship_type)?->label() ?? $this->relationship_type,
            'eligible_from' => $this->eligible_from?->format('d/m/Y'),
            'eligible_until' => $this->eligible_until?->format('d/m/Y'),
            'status' => $this->status,
            'status_label' => DependentRelationStatusEnum::tryFrom($this->status)?->label() ?? $this->status,
            'note' => $this->note,
        ];
    }
}
