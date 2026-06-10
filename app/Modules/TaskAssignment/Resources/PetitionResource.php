<?php

namespace App\Modules\TaskAssignment\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetitionResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
            ]),
            'submission_date' => $this->submission_date?->format('d/m/Y'),
            'deadline_date' => $this->deadline_date?->format('d/m/Y'),
            'sender_name' => $this->sender_name,
            'sender_address' => $this->sender_address,
            'sender_cccd' => $this->sender_cccd,
            'sender_phone' => $this->sender_phone,
            'sender_email' => $this->sender_email,
            'content' => $this->content,
            'processing_status' => $this->processing_status,
            'timing_status' => $this->resource->timingStatus(),
            'is_overdue' => $this->resource->isOverdue(),
            'completed_at' => $this->completed_at?->format('H:i:s d/m/Y'),
            'document_number' => $this->document_number,
            'document_excerpt' => $this->document_excerpt,
            'response_content' => $this->response_content,
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'media_id' => $attachment->media_id,
                        'file_name' => $attachment->file_name,
                        'sort_order' => $attachment->sort_order,
                        'url' => $attachment->media ? '/storage/' . $attachment->media->id . '/' . $attachment->media->file_name : null,
                        'original_name' => $attachment->media?->file_name,
                        'mime_type' => $attachment->media?->mime_type,
                        'size' => $attachment->media?->size,
                    ];
                });
            }),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
