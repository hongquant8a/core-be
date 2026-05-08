<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckinByTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'uuid' => ':attribute phải đúng định dạng UUID.',
        ];
    }

    public function attributes(): array
    {
        return [
            'token' => 'Mã QR điểm danh',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'token' => [
                'description' => 'UUID checkin_token của meeting (FE đọc từ QR scan).',
                'example' => '550e8400-e29b-41d4-a716-446655440000',
            ],
        ];
    }
}
