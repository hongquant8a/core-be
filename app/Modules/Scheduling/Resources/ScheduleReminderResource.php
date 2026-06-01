<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'organization_id'          => $this->organization_id,
            'schedule_id'              => $this->schedule_id,
            'notification_schedule_id' => $this->notification_schedule_id,
            'reminder_type'            => $this->reminder_type,
            'moment'                   => $this->moment,
            'offset_minutes'           => $this->offset_minutes,
            'channels'                 => $this->channels,
            'scheduled_at'             => $this->scheduled_at?->toIso8601String(),
            'sent_at'                  => $this->sent_at?->toIso8601String(),
            'status'                   => $this->status,
            'message'                  => $this->message,
        ];
    }
}
