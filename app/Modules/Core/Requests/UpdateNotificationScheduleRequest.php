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
            'channels.*' => 'in:sms,mail,zalo,zalo_zns,fcm',
            'label' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer',
        ];
    }

    public function attributes(): array
    {
        return [
            'moment' => 'Moment',
            'offset_minutes' => 'Nhắc trước (phút)',
            'channels' => 'Kênh thông báo',
            'channels.*' => 'Kênh thông báo',
            'label' => 'Nhãn',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
}
