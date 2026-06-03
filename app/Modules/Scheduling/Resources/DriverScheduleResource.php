<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverScheduleResource extends JsonResource
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

        return [
            'id'                   => $this->id,
            'content'              => $this->content,
            'location'             => $this->location,
            'session'              => $sessionVal,
            'date_time'            => $this->date_time?->toIso8601String(),
            'preparation_unit'     => $this->preparation_unit,
            'driver_text'       => $this->driver_text,
            'participants_text' => $this->participants_text,
            'is_important'         => $this->is_important,
            'status'               => $statusVal,
            'host'                 => $this->host ? [
                'id'   => $this->host->id,
                'name' => $this->host->name,
            ] : ($this->host_text ? [
                'id'   => null,
                'name' => $this->host_text,
            ] : null),
            'host_text'            => $this->host_text,
        ];
    }
}
