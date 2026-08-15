<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Resources\Concerns\HasParentLockVersion;
use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryDependentResource extends JsonResource
{
    use FormatsUserSummary, HasParentLockVersion;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_id' => $this->beneficiary_id,
            'full_name' => $this->full_name,
            'birth_date' => $this->birth_date?->format('d/m/Y'),
            'birth_year' => $this->birth_year,
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'id_number' => $this->id_number,
            'phone' => $this->phone,
            'residential_area_id' => $this->residential_area_id,
            'residential_area' => new BeneficiaryResidentialAreaResource($this->whenLoaded('residentialArea')),
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'note' => $this->note,
            'relationship_id' => $this->relationship_id,
            'relationship' => new BeneficiaryRelationshipResource($this->whenLoaded('relationship')),
            'is_primary' => $this->is_primary,

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            'parent_lock_version' => $this->parentLockVersion(),
        ];
    }
}
