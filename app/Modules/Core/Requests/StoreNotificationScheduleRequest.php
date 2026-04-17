<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'moment' => 'nullable|in:before,on,after',
            'offset_minutes' => 'nullable|integer|min:0',
            'channels' => 'array',
            'channels.*' => 'in:sms,mail,zalo,fcm',
            'label' => 'required|string|max:255',
            'sort_order' => 'integer',
        ];
    }
}
