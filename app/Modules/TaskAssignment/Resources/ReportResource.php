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
            // Timing so với deadline task: 'on_time' | 'late' | null
            'timing_status' => $this->resource->timingStatus(),
            'report_document_number' => $this->report_document_number,
            'report_document_excerpt' => $this->report_document_excerpt,
            'report_document_content' => $this->report_document_content,
            'manager_confirmed' => (bool) $this->manager_confirmed,
            'manager_confirmed_by' => $this->whenLoaded('managerConfirmer', fn () => [
                'id' => $this->managerConfirmer?->id,
                'name' => $this->managerConfirmer?->name,
                'email' => $this->managerConfirmer?->email,
            ], $this->manager_confirmed_by),
            'manager_confirmed_at' => $this->manager_confirmed_at?->format('H:i:s d/m/Y'),
            'manager_confirm_note' => $this->manager_confirm_note,
            'is_locked' => (bool) $this->is_locked,
            'locked_by' => $this->whenLoaded('locker', fn () => [
                'id' => $this->locker?->id,
                'name' => $this->locker?->name,
                'email' => $this->locker?->email,
            ], $this->locked_by),
            'locked_at' => $this->locked_at?->format('H:i:s d/m/Y'),
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'media_id' => $attachment->media_id,
                        'file_name' => $attachment->file_name,
                        'sort_order' => $attachment->sort_order,
                        'url' => $attachment->media ? '/storage/'.$attachment->media->id.'/'.$attachment->media->file_name : null,
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
