<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReminderPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'moment' => 'required|string|in:before,after',
            'offset_minutes' => 'required|integer|min:0',
            'label' => 'required|string|max:100',
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:fcm,zalo,sms,inapp,FCM,ZALO,SMS,APP',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'in' => ':attribute không đúng định dạng cho phép.',
            'integer' => ':attribute phải là số nguyên.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'array' => ':attribute phải là mảng.',
        ];
    }

    public function attributes(): array
    {
        return [
            'moment' => 'Thời điểm nhắc',
            'offset_minutes' => 'Số phút lệch',
            'label' => 'Nhãn hiển thị',
            'channels' => 'Kênh gửi',
            'channels.*' => 'Tên kênh gửi',
        ];
    }
}
