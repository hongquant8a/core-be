<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'residential_area_id' => $this->residential_area_id,
            'residential_area' => $this->whenLoaded('residentialArea', fn () => [
                'id' => $this->residentialArea->id,
                'name' => $this->residentialArea->name,
            ]),
            'household_code' => $this->household_code,
            'head_name' => $this->head_name,
            'head_id_number' => $this->head_id_number,
            'address' => $this->address,
            'phone' => $this->phone,
            'member_count' => $this->member_count,
            'note' => $this->note,
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
