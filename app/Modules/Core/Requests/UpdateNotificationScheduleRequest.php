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

    public function messages(): array
    {
        return [
            'moment.in' => 'Moment không hợp lệ.',
            'offset_minutes.integer' => 'Nhắc trước (phút) phải là số nguyên.',
            'offset_minutes.min' => 'Nhắc trước (phút) không được nhỏ hơn 0.',
            'channels.array' => 'Kênh thông báo phải là mảng.',
            'channels.*.in' => 'Kênh thông báo không hợp lệ.',
            'label.string' => 'Nhãn phải là chuỗi ký tự.',
            'label.max' => 'Nhãn không được vượt quá 255 ký tự.',
            'sort_order.integer' => 'Thứ tự sắp xếp phải là số nguyên.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
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
