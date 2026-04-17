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
            'moment' => 'sometimes|nullable|in:before,on,after',
            'offset_minutes' => 'sometimes|nullable|integer|min:0',
            'channels' => 'sometimes|array',
            'channels.*' => 'in:sms,mail,zalo,fcm',
            'label' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
