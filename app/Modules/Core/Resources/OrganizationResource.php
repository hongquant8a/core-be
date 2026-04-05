<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
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
            'depth' => $this->depth,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null, null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->editor ? ['id' => $this->editor->id, 'name' => $this->editor->name] : null, null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
            'parent' => $this->whenLoaded('parent', fn () => new OrganizationResource($this->parent)),
            'children' => $this->whenLoaded('children', fn () => OrganizationResource::collection($this->children)),
        ];
    }
}
