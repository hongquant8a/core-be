<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTestNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels'      => ['required', 'array', 'min:1'],
            'channels.*'    => ['required', 'string', 'in:sms,mail,zalo'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'email'         => ['nullable', 'email', 'max:255'],
            'zalo_id'       => ['nullable', 'string', 'max:100'],
            'name'          => ['nullable', 'string', 'max:255'],
            'content'       => ['required', 'string', 'max:500'],
            'subject'       => ['nullable', 'string', 'max:255'],
            'context'       => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'channels.required'   => 'Phải chọn ít nhất một kênh gửi.',
            'channels.array'      => 'Danh sách kênh phải là mảng.',
            'channels.*.in'       => 'Kênh không hợp lệ. Chỉ chấp nhận: sms, mail, zalo.',
            'content.required'    => 'Nội dung là bắt buộc.',
            'email.email'         => 'Email không hợp lệ.',
        ];
    }
}
