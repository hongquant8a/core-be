<?php

namespace App\Modules\Scheduling\Resources;

use App\Modules\Core\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'organization_id'      => $this->organization_id,
            'module_type'          => $this->module_type,
            'title'                => $this->title,
            'content'              => $this->content,
            'location'             => $this->location,
            'session'              => $this->session,
            'date'                 => $this->date->toDateString(),
            'start_time'           => $this->start_time,
            'end_time'             => $this->end_time,

            'host_user_id'         => $this->host_user_id,
            'host'                 => new UserResource($this->whenLoaded('host')),
            'driver_user_id'       => $this->driver_user_id,
            'driver'               => new UserResource($this->whenLoaded('driver')),
            'preparation_location' => $this->preparation_location,

            'status'               => $this->status,
            'approved_by'          => $this->approved_by,
            'approved_at'          => $this->approved_at?->toIso8601String(),
            'rejection_note'       => $this->rejection_note,
            'approver'             => new UserResource($this->whenLoaded('approver')),

            'sort_order'           => $this->sort_order,
            'is_recurring'         => $this->is_recurring,
            'recurrence_rule'      => $this->recurrence_rule,
            'parent_schedule_id'   => $this->parent_schedule_id,

            'participants'         => ScheduleParticipantResource::collection($this->whenLoaded('participants')),
            'reminders'            => ScheduleReminderResource::collection($this->whenLoaded('reminders')),
            'attachments'          => $this->whenLoaded('media', function () {
                return $this->media->map(fn ($m) => [
                    'id'        => $m->id,
                    'file_name' => $m->file_name,
                    'file_url'  => $m->getUrl(),
                    'mime_type' => $m->mime_type,
                    'size'      => $m->size,
                ]);
            }),

            'created_by'           => $this->created_by,
            'creator'              => new UserResource($this->whenLoaded('creator')),
            'updated_by'           => $this->updated_by,
            'editor'               => new UserResource($this->whenLoaded('editor')),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}


