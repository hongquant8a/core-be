<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SsoExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:sso_danang',
            'code' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'Provider không hợp lệ.',
            'code.required' => 'Thiếu authorization code.',
        ];
    }

    public function attributes(): array
    {
        return [
            'provider' => 'Nhà cung cấp',
            'code' => 'Mã',
        ];
    }
}
