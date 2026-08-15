<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryResidentialAreaResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'note' => $this->note,
            'sort_order' => $this->sort_order,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            // Đếm số hồ sơ/thân nhân đang tham chiếu — FE dùng để giải thích vì sao
            // nút Xoá bị chặn. Chỉ có khi service gọi withCount().
            'usage_count' => $this->when(
                isset($this->beneficiaries_count) || isset($this->dependents_count),
                fn () => (int) ($this->beneficiaries_count ?? 0) + (int) ($this->dependents_count ?? 0)
            ),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
            'lock_version' => $this->updated_at?->toIso8601String(),
        ];
    }
}
