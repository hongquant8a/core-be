<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'moment' => 'sometimes|in:before,on,after',
            'offset_minutes' => 'nullable|integer|min:0',
            'channels' => 'sometimes|array|min:1',
            'channels.*' => 'in:sms,mail,zalo,fcm',
            'enabled' => 'sometimes|boolean',
            'label' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
