<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubsidyPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_type' => $this->beneficiary_type,
            'beneficiary_type_label' => BeneficiaryTypeEnum::tryFrom((string) $this->beneficiary_type)?->label(),
            'relationship_type' => $this->relationship_type,
            'relationship_type_label' => DependentRelationshipEnum::tryFrom((string) $this->relationship_type)?->label(),
            'amount' => $this->amount,
            'unit' => $this->unit,
            'legal_basis' => $this->legal_basis,
            'effective_from' => $this->effective_from?->format('d/m/Y'),
            'effective_to' => $this->effective_to?->format('d/m/Y'),
            'is_effective' => is_null($this->effective_to) || $this->effective_to->isFuture(),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
