<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Scheduling\Enums\ScheduleModuleTypeEnum;
use App\Modules\Scheduling\Enums\ScheduleSessionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_type' => ['required', ScheduleModuleTypeEnum::rule()],
            'title'       => ['required', 'string', 'max:500'],
            'content'     => ['nullable', 'string'],
            'location'    => ['nullable', 'string', 'max:500'],
            'session'     => ['required', ScheduleSessionEnum::rule()],
            'date'        => ['required', 'date_format:Y-m-d'],
            'start_time'  => ['nullable', 'date_format:H:i:s,H:i'],
            'end_time'    => ['nullable', 'date_format:H:i:s,H:i'],
            'status'      => ['nullable', 'string'],

            'host_user_id'         => ['nullable', 'integer', 'exists:users,id'],
            'driver_user_id'       => ['nullable', 'integer', 'exists:users,id'],
            'preparation_location' => ['nullable', 'string', 'max:500'],

            'sort_order'         => ['nullable', 'integer', 'min:0'],
            'is_recurring'       => ['nullable', 'boolean'],
            'recurrence_rule'    => ['nullable', 'array'],
            'parent_schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],

            'participants'                  => ['nullable', 'array'],
            'participants.*.user_id'        => ['nullable', 'integer', 'exists:users,id'],
            'participants.*.display_name'   => ['nullable', 'string', 'max:255'],
            'participants.*.position_name'  => ['nullable', 'string', 'max:255'],
            'participants.*.is_external'    => ['nullable', 'boolean'],

            'reminders'                  => ['nullable', 'array'],
            'reminders.*.reminder_type'  => ['required_without:reminders.*.source', 'nullable', 'in:PRESET,CUSTOM'],
            'reminders.*.source'         => ['required_without:reminders.*.reminder_type', 'nullable', 'in:preset,custom,PRESET,CUSTOM'],
            'reminders.*.moment'         => ['nullable', 'in:BEFORE,ON,AFTER'],
            'reminders.*.offset_minutes' => ['required_without:reminders.*.minutes_before', 'nullable', 'integer'],
            'reminders.*.minutes_before' => ['required_without:reminders.*.offset_minutes', 'nullable', 'integer'],
            'reminders.*.channels'       => ['required', 'array'],
            'reminders.*.channels.*'     => ['string'],

            'files'   => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ];
    }
}
