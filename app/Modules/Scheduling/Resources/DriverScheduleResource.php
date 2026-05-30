<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverScheduleResource extends JsonResource
{
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
            ] : null,
            'location' => $this->location,
            'nature' => $this->nature,
            'driver_id' => $this->driver_id,
            'driver' => $this->driver ? [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ] : null,
            'color_code' => $this->color_code,
            'status' => $this->status,
            'week_number' => $this->week_number,
            'year' => $this->year,
            // Driver is not allowed to see content, participants_text, departments_text, attachments, reminders, etc.
        ];
    }
}
