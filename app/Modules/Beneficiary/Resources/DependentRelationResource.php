<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
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
            'dependent_id' => $this->dependent_id,
            'dependent' => $this->whenLoaded('dependent', fn () => $this->dependent ? [
                'id' => $this->dependent->id,
                'full_name' => $this->dependent->full_name,
                'date_of_birth' => $this->dependent->date_of_birth?->format('d/m/Y'),
                'id_number' => $this->dependent->id_number,
                'phone' => $this->dependent->phone,
            ] : null),
            'relationship_type' => $this->relationship_type,
            'relationship_type_label' => DependentRelationshipEnum::tryFrom($this->relationship_type)?->label() ?? $this->relationship_type,
            'note' => $this->note,
        ];
    }
}
