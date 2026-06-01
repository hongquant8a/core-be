<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchedulingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_enabled'      => ['required', 'boolean'],
            'approval_module_types' => ['nullable', 'array'],
            'approval_module_types.*' => ['string', 'in:EXECUTIVE,OFFICE'],
            'default_channels'      => ['required', 'array'],
            'default_channels.*'    => ['string', 'in:inapp,fcm,zalo'],
            'working_sessions'      => ['required', 'array'],
            'working_sessions.MORNING' => ['required', 'array'],
            'working_sessions.MORNING.start' => ['required', 'string', 'date_format:H:i'],
            'working_sessions.MORNING.end' => ['required', 'string', 'date_format:H:i'],
            'working_sessions.AFTERNOON' => ['required', 'array'],
            'working_sessions.AFTERNOON.start' => ['required', 'string', 'date_format:H:i'],
            'working_sessions.AFTERNOON.end' => ['required', 'string', 'date_format:H:i'],
            'working_sessions.EVENING' => ['required', 'array'],
            'working_sessions.EVENING.start' => ['required', 'string', 'date_format:H:i'],
            'working_sessions.EVENING.end' => ['required', 'string', 'date_format:H:i'],
        ];
    }
}
