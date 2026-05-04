<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingDocumentResource extends JsonResource
{
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
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($att) => [
                'id' => $att->id,
                'media_id' => $att->media_id,
                'file_name' => $att->file_name,
                'file_url' => $att->media?->getUrl(),
                'sort_order' => $att->sort_order,
            ])->values()->all(), []),
            'is_public' => $this->is_public,
            'status' => $this->status,
            'view_count' => $this->view_count,
            'download_count' => $this->download_count,
            'sort_order' => $this->sort_order,
            'created_by' => $this->creator?->name ?? 'N/A',
            'updated_by' => $this->editor?->name ?? 'N/A',
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
