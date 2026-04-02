<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_assignment_item_id' => $this->task_assignment_item_id,
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
                'email' => $this->reporter?->email,
            ]),
            'completed_at' => $this->completed_at?->format('H:i:s d/m/Y'),
            'report_document_number' => $this->report_document_number,
            'report_document_excerpt' => $this->report_document_excerpt,
            'report_document_content' => $this->report_document_content,
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'media_id' => $attachment->media_id,
                        'file_name' => $attachment->file_name,
                        'sort_order' => $attachment->sort_order,
                        'url' => $attachment->media?->getUrl(),
                        'original_name' => $attachment->media?->file_name,
                        'mime_type' => $attachment->media?->mime_type,
                        'size' => $attachment->media?->size,
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
