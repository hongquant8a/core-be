<?php

namespace App\Modules\Meeting\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingDocumentResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_agenda_id' => $this->meeting_agenda_id,
            'meeting_document_type_id' => $this->meeting_document_type_id,
            'meeting_document_type_name' => $this->documentType?->name,
            'title' => $this->title,
            'document_number' => $this->document_number,
            'summary' => $this->summary,
            'media_id' => $this->media_id,
            'file_url' => $this->mediaFile ? '/storage/'.$this->mediaFile->id.'/'.$this->mediaFile->file_name : null,
            'file_name' => $this->mediaFile?->file_name,
            'is_public' => $this->is_public,
            'download_count' => $this->download_count,
            'view_count' => $this->view_count,
            'sort_order' => $this->sort_order,
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
