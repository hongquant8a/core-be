<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'title'                => $this->title,
            'location'             => $this->location,
            'session'              => $this->session,
            'date'                 => $this->date->toDateString(),
            'start_time'           => $this->start_time,
            'end_time'             => $this->end_time,
            'preparation_location' => $this->preparation_location,
            'status'               => $this->status,
            'host'                 => $this->host ? [
                'id'   => $this->host->id,
                'name' => $this->host->name,
            ] : null,
        ];
    }
}
