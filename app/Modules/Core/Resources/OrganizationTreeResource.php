<?php

namespace App\Modules\Core\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource cho API tree organization (cấu trúc cây parent_id). */
class OrganizationTreeResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'user_count' => $this->user_count ?? 0,
            'depth' => $this->depth,
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
            'children' => $this->whenLoaded(
                'children',
                fn () => OrganizationTreeResource::collection($this->children),
                []
            ),
        ];
    }
}
