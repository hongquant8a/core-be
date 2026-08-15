<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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

            'type_relations' => BeneficiaryTypeRelationResource::collection($this->whenLoaded('typeRelations')),
            'dependents' => BeneficiaryDependentResource::collection($this->whenLoaded('dependents')),
            'documents' => BeneficiaryDocumentResource::collection($this->whenLoaded('documents')),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),

            // updated_at để HIỂN THỊ, theo format chung của Danatec.
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            // lock_version là TOKEN khoá lạc quan — ISO8601, KHÔNG format lại.
            // Format d/m/Y mất phần giây → so sánh sai vĩnh viễn.
            'lock_version' => $this->updated_at?->toIso8601String(),
        ];
    }
}
