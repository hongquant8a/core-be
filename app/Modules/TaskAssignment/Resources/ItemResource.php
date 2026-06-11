<?php

namespace App\Modules\TaskAssignment\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    use FormatsUserSummary;
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'document' => new DocumentResource($this->whenLoaded('document')),
            'item_type' => new LookupResource($this->whenLoaded('itemType')),
            'deadline_type' => $this->deadline_type,
            'start_at' => $this->start_at?->format('H:i:s d/m/Y'),
            'end_at' => $this->end_at?->format('H:i:s d/m/Y'),
            'processing_status' => $this->processing_status,
            'completion_percent' => $this->completion_percent,
            'priority' => $this->priority,
            'completed_at' => $this->completed_at?->format('H:i:s d/m/Y'),
            'is_overdue' => $this->resource->isOverdue(),
            'timing_status' => $this->resolveTimingStatus(),
            'departments' => $this->whenLoaded('users', function () {
                $deptIds = $this->users->pluck('pivot.department_id')->unique();
                $depts = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::whereIn('id', $deptIds)->get()->keyBy('id');

                return $this->users->groupBy('pivot.department_id')->map(function ($group, $deptId) use ($depts) {
                    $dept = $depts->get($deptId);

                    return [
                        'id' => $dept?->id,

                        'name' => $dept?->name,
                        'role' => $group->first()->pivot->department_role,
                    ];
                })->values();
            }),
            'users' => $this->whenLoaded('users', function () {
                return $this->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'department_id' => $user->pivot->department_id,
                        'department_role' => $user->pivot->department_role,
                        'assignment_role' => $user->pivot->assignment_role,
                        'assignment_status' => $user->pivot->assignment_status,
                        'assigned_at' => $user->pivot->assigned_at ? \Carbon\Carbon::parse($user->pivot->assigned_at)->format('H:i:s d/m/Y') : null,
                        'accepted_at' => $user->pivot->accepted_at ? \Carbon\Carbon::parse($user->pivot->accepted_at)->format('H:i:s d/m/Y') : null,
                        'completed_at' => $user->pivot->completed_at ? \Carbon\Carbon::parse($user->pivot->completed_at)->format('H:i:s d/m/Y') : null,
                        'note' => $user->pivot->note,
                    ];
                });
            }),
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
            'reports_count' => $this->whenCounted('reports'),
            'transfers_count' => $this->whenCounted('transfers'),
            'notes_count' => $this->whenCounted('notes'),
            'assigned_by' => $this->whenLoaded('assigner', fn () => $this->formatUserSummary($this->assigner), null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            // Reminder per-record — chỉ trả CUSTOM, PRESET là nội bộ hệ thống.
            'reminders' => $this->whenLoaded('reminders', function () {
                return TaskAssignmentReminderResource::collection(
                    $this->reminders->where('source', 'CUSTOM')
                );
            }),
        ];
    }

    private function resolveTimingStatus(): string
    {
        $done = \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value;
        $cancelled = \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = \App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum::HasDeadline->value;

        if ($this->processing_status === $cancelled) {
            return 'cancelled';
        }

        if ($this->processing_status === $done) {
            if ($this->deadline_type === $hasDeadline && $this->end_at && $this->completed_at) {
                $completedAt = \Carbon\Carbon::parse($this->completed_at);
                $endAt = \Carbon\Carbon::parse($this->end_at);

                if ($completedAt->toDateString() < $endAt->toDateString()) {
                    return 'early';
                }
                if ($completedAt->toDateString() > $endAt->toDateString()) {
                    return 'late';
                }
            }
            return 'on_time';
        }

        if ($this->deadline_type === $hasDeadline && $this->end_at && \Carbon\Carbon::parse($this->end_at)->lt(now())) {
            return 'overdue';
        }

        return 'upcoming';
    }
}
