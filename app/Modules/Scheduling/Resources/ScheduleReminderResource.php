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
            'schedule_id'    => $this->schedule_id,
            'minutes_before' => $this->minutes_before,
            'offset_minutes' => $this->minutes_before,
            'channels'       => $this->channels,
            'source'         => $sourceVal,
            'reminder_type'  => strtoupper($sourceVal ?? 'CUSTOM'),
        ];
    }
}
