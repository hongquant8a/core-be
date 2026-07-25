<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DependentResource extends JsonResource
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
            'residential_area_id' => $this->residential_area_id,
            'residential_area' => $this->whenLoaded('residentialArea', fn () => $this->residentialArea ? [
                'id' => $this->residentialArea->id,
                'name' => $this->residentialArea->name,
            ] : null),

            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth?->format('d/m/Y'),
            'gender' => $this->gender,
            'gender_label' => GenderEnum::tryFrom((string) $this->gender)?->label() ?? $this->gender,
            'id_number' => $this->id_number,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'note' => $this->note,

            'relations' => DependentRelationResource::collection($this->whenLoaded('dependentRelations')),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
