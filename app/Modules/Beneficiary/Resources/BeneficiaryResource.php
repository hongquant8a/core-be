<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
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
            'household_id' => $this->household_id,
            'household' => $this->whenLoaded('household', fn () => $this->household ? [
                'id' => $this->household->id,
                'head_name' => $this->household->head_name,
            ] : null),

            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth?->format('d/m/Y'),
            'birth_year' => $this->birth_year,
            'gender' => $this->gender,
            'gender_label' => GenderEnum::tryFrom((string) $this->gender)?->label() ?? $this->gender,
            'id_number' => $this->id_number,
            'status' => $this->status,
            'status_label' => BeneficiaryStatusEnum::tryFrom((string) $this->status)?->label() ?? $this->status,
            'death_date' => $this->death_date?->format('d/m/Y'),
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'note' => $this->note,

            'classifications' => BeneficiaryClassificationResource::collection($this->whenLoaded('classifications')),
            'documents' => BeneficiaryDocumentResource::collection($this->whenLoaded('documents')),
            'dependents_count' => $this->whenCounted('dependents'),
            'documents_count' => $this->whenCounted('documents'),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
