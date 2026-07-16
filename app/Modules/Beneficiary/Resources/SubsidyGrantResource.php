<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubsidyGrantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->id,
                'name' => $this->subject->full_name ?? null,
            ] : null),
            'beneficiary_subsidy_policy_id' => $this->beneficiary_subsidy_policy_id,
            'policy' => $this->whenLoaded('policy', fn () => $this->policy ? [
                'id' => $this->policy->id,
                'legal_basis' => $this->policy->legal_basis,
            ] : null),
            'amount' => $this->amount,
            'granted_from' => $this->granted_from?->format('d/m/Y'),
            'granted_to' => $this->granted_to?->format('d/m/Y'),
            'status' => $this->status,
            'status_label' => SubsidyStatusEnum::tryFrom((string) $this->status)?->label() ?? $this->status,
            'termination_reason' => $this->termination_reason,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
