<?php

namespace App\Modules\Scheduling\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'module_type' => $this->module_type,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'session' => $this->session,
            'content' => $this->content,
            'host_id' => $this->host_id,
            'host' => $this->host ? [
                'id' => $this->host->id,
                'name' => $this->host->name,
                'priority_weight' => $this->host->priority_weight,
            ] : null,
            'host_priority_weight' => $this->host_priority_weight,
            'location' => $this->location,
            'preparation_unit' => $this->preparation_unit,
            'participant_count' => $this->participant_count,
            'nature' => $this->nature,
            'driver_id' => $this->driver_id,
            'driver' => $this->driver ? [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ] : null,
            'color_code' => $this->color_code,
            'participants_text' => $this->participants_text,
            'departments_text' => $this->departments_text,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'week_number' => $this->week_number,
            'year' => $this->year,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Loaded relations
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($att) => [
                'id' => $att->id,
                'media_id' => $att->media_id,
                'file_name' => $att->file_name,
                'sort_order' => $att->sort_order,
                'url' => $att->mediaFile ? '/storage/'.$att->mediaFile->id.'/'.$att->mediaFile->file_name : null,
                'size' => $att->mediaFile?->size,
                'mime_type' => $att->mediaFile?->mime_type,
            ])),
            
            'recipients' => $this->whenLoaded('recipients', fn () => $this->recipients->map(fn ($rec) => [
                'id' => $rec->id,
                'user_id' => $rec->user_id,
                'user' => $rec->user ? [
                    'id' => $rec->user->id,
                    'name' => $rec->user->name,
                ] : null,
                'group_id' => $rec->group_id,
                'group' => $rec->group ? [
                    'id' => $rec->group->id,
                    'name' => $rec->group->name,
                ] : null,
            ])),

            'reminders' => $this->whenLoaded('reminders', fn () => $this->reminders->map(fn ($rem) => [
                'id' => $rem->id,
                'minutes_before' => $rem->minutes_before,
                'channels' => $rem->channels,
                'source' => $rem->source,
                'preset_id' => $rem->preset_id,
                'preset_label' => $rem->preset?->label,
            ])),
        ];
    }
}
