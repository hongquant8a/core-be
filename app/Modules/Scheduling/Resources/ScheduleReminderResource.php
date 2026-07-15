<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sourceVal = $this->source;
        if (is_object($sourceVal) && method_exists($sourceVal, 'value')) {
            $sourceVal = $sourceVal->value;
        }

        return [
            'id'             => $this->id,
            'schedule_id'    => $this->schedule_id ?? $this->remindable_id,
            'moment'         => $this->moment,
            'offset_minutes' => $this->offset_minutes,
            'channels'       => $this->channels,
            'status'         => $this->status,
            'fired_at'       => $this->fired_at?->format('H:i:s d/m/Y'),
            'source'         => $sourceVal,
            'reminder_type'   => $this->reminder_type, // instant | scheduled (computed từ moment)
            // Deprecated
            'minutes_before' => $this->offset_minutes,
            'trigger'        => $this->moment,
        ];
    }
}
