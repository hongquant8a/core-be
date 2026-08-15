<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use App\Modules\Beneficiary\Resources\Concerns\HasParentLockVersion;
use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\Core\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryDocumentResource extends JsonResource
{
    use FormatsUserSummary, HasParentLockVersion;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_id' => $this->beneficiary_id,
            'name' => $this->name,
            'note' => $this->note,

            'files' => $this->whenLoaded('media', fn () => MediaResource::collection(
                $this->getMedia(BeneficiaryDocument::MEDIA_COLLECTION)
            )),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            'parent_lock_version' => $this->parentLockVersion(),
        ];
    }
}
