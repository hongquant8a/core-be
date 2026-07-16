<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusHistoryResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'reason' => $this->reason,
            'changed_by' => $this->whenLoaded('changer', fn () => $this->changer ? $this->formatUserSummary($this->changer) : null),
            'changed_at' => $this->changed_at?->format('H:i:s d/m/Y'),
        ];
    }
}
