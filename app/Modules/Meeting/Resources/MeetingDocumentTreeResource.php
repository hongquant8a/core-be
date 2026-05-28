<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingDocumentTreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $groupedDocs = [];
        
        if ($this->relationLoaded('documents')) {
            foreach ($this->documents as $doc) {
                // If the document has no type, default to 0 (Uncategorized)
                $typeId = $doc->meeting_document_type_id ?: 0;
                
                if (!isset($groupedDocs[$typeId])) {
                    $groupedDocs[$typeId] = [
                        'id' => $typeId,
                        'name' => $doc->documentType ? $doc->documentType->name : 'Tài liệu khác',
                    ];
                }
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time?->format('H:i:s d/m/Y'),
            'end_time' => $this->end_time?->format('H:i:s d/m/Y'),
            'status' => $this->status,
            'meeting_type_name' => $this->meetingType?->name,
            'meeting_location_name' => $this->meetingLocation?->name,
            'document_tree' => array_values($groupedDocs),
        ];
    }
}
