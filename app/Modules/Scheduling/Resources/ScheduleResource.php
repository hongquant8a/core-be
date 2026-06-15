<?php

namespace App\Modules\Scheduling\Resources;

use App\Modules\Core\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusVal = $this->status;
        if (is_object($statusVal) && method_exists($statusVal, 'value')) {
            $statusVal = $statusVal->value;
        }

        $sessionVal = $this->session;
        if (is_object($sessionVal) && method_exists($sessionVal, 'value')) {
            $sessionVal = $sessionVal->value;
        }

        $moduleVal = $this->module_type;
        if (is_object($moduleVal) && method_exists($moduleVal, 'value')) {
            $moduleVal = $moduleVal->value;
        }

        $natureVal = $this->nature;
        if (is_object($natureVal) && method_exists($natureVal, 'value')) {
            $natureVal = $natureVal->value;
        }

        return [
            'id'                   => $this->id,
            'organization_id'      => $this->organization_id,
            'module_type'          => $moduleVal,
            'content'              => $this->content,
            'location'             => $this->location,
            'session'              => $sessionVal,
            'date_time'            => $this->date_time?->toIso8601String(),

            // Host & Driver
            'host_id'              => $this->host_id,
            'host_text'            => $this->host_text,
            'host'                 => new UserResource($this->whenLoaded('host')),
            'driver_id'            => $this->driver_id,
            'driver_text'          => $this->driver_text,
            'driver'               => new UserResource($this->whenLoaded('driver')),
            
            // Locations, Departments & Count
            'preparation_unit'     => $this->preparation_unit,
            'departments_text'     => $this->departments_text,
            'participants_text'    => $this->participants_text,
            'participant_count'    => $this->participant_count,
            'nature'               => $natureVal,
            'is_important'         => $this->is_important,
            
            // Approval fields
            'status'               => $statusVal,
            'approval_status'      => $this->approval_status,
            'approved_by'          => $this->approved_by,
            'approved_at'          => $this->approved_at?->toIso8601String(),
            'rejection_note'       => $this->rejection_note,
            'approver'             => new UserResource($this->whenLoaded('approver')),

            'sort_order'           => $this->sort_order,
            'week_number'          => $this->week_number,
            'year'                 => $this->year,

            // Relations
            'recipients'           => $this->whenLoaded('recipients', function () {
                return $this->recipients->map(fn ($r) => [
                    'id'           => $r->id,
                    'user_id'      => $r->user_id,
                    'display_name' => $r->display_name,
                    'user'     => $r->user ? [
                        'id'    => $r->user->id,
                        'name'  => $r->user->name,
                        'email' => $r->user->email,
                    ] : null,
                ]);
            }),
            'participants'         => $this->whenLoaded('recipients', function () {
                return $this->recipients->map(fn ($r) => [
                    'id'            => $r->id,
                    'user_id'       => $r->user_id,
                    'display_name'  => $r->display_name ?? $r->user?->name,
                    'position_name' => $r->user?->position_name,
                    'is_external'   => empty($r->user_id),
                ]);
            }),
            'reminders'            => $this->whenLoaded('reminders', function () {
                return ScheduleReminderResource::collection(
                    $this->reminders->where('source', 'CUSTOM')
                );
            }),
            'attachments'          => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(fn ($m) => [
                    'id'            => $m->id,
                    'media_id'      => null,
                    'name'          => $m->title ?: $m->file_name,
                    'file_name'     => $m->file_name,
                    'file_path'     => '/storage/' . $m->file_path,
                    'sort_order'    => $m->sort_order ?? 0,
                    'url'           => '/storage/' . $m->file_path,
                    'mime_type'     => $m->mime_type,
                    'size'          => $m->file_size,
                ]);
            }),

            'created_by'           => new UserResource($this->whenLoaded('creator')),
            'updated_by'           => new UserResource($this->whenLoaded('editor')),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
