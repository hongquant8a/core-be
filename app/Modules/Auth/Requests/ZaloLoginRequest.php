<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ZaloLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zaloToken' => ['required', 'string'],
            'phoneToken' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'zaloToken.required' => 'Zalo Token không được để trống.',
            'phoneToken.required' => 'Phone Token không được để trống.',
        ];
    }
}
