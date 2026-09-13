<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTelegramWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Bỏ trống thì service lấy cấu hình tg_webhook_url, không có nữa thì APP_URL.
            'url' => ['nullable', 'string', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.string' => 'Domain webhook phải là chuỗi.',
            'url.url' => 'Domain webhook không hợp lệ. Ví dụ đúng: https://api-qlcv.danatec.vn',
            'url.max' => 'Domain webhook không được vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'url' => 'Domain webhook',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'url' => [
                'description' => 'Domain HTTPS ghi đè. Bỏ trống thì dùng cấu hình đã lưu hoặc APP_URL.',
                'example' => 'https://api-qlcv.danatec.vn',
            ],
        ];
    }
}
