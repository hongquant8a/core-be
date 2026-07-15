<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskAssignmentReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sourceVal = $this->source;
        if (is_object($sourceVal) && method_exists($sourceVal, 'value')) {
            $sourceVal = $sourceVal->value;
        }

        return [
            'id'              => $this->id,
            'task_assignment_document_id' => $this->task_assignment_document_id ?? $this->remindable?->task_assignment_document_id,
            'task_assignment_item_id' => $this->task_assignment_item_id ?? $this->remindable_id,
            'moment'          => $this->moment,
            'offset_minutes'  => $this->offset_minutes,
            'channels'        => $this->channels,
            'status'          => $this->status,
            'remind_at'       => $this->remind_at?->format('H:i:s d/m/Y'),
            'fired_at'        => $this->fired_at?->format('H:i:s d/m/Y'),
            'source'          => $sourceVal,
            'reminder_type'   => $this->reminder_type, // instant | scheduled
            // Deprecated — giữ backward compat với FE cũ
            'minutes_before'  => $this->offset_minutes,
            'trigger'         => $this->moment,
        ];
    }
}
