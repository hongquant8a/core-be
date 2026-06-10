<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sourceVal = $this->source;
        if (is_object($sourceVal) && method_exists($sourceVal, 'value')) {
            $sourceVal = $sourceVal->value;
        }

        return [
            'id'              => $this->id,
            'meeting_id'      => $this->meeting_id,
            'moment'          => $this->moment,
            'offset_minutes'  => $this->offset_minutes,
            'channels'        => $this->channels,
            'status'          => $this->status,
            'remind_at'       => $this->remind_at?->format('H:i:s d/m/Y'),
            'fired_at'        => $this->fired_at?->format('H:i:s d/m/Y'),
            'source'          => $sourceVal,
            'reminder_type'   => strtoupper($sourceVal ?? 'CUSTOM'),
            // Deprecated — giữ backward compat với FE cũ
            'minutes_before'  => $this->offset_minutes,
            'trigger'         => $this->moment,
        ];
    }
}
