<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate request đăng nhập.
 * Cho phép đăng nhập bằng email hoặc user_name.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string',
            'password' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email hoặc tên đăng nhập không được để trống.',
            'password.required' => 'Mật khẩu không được để trống.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Email hoặc tên đăng nhập (user_name)',
                'example' => 'admin@example.com',
            ],
            'password' => [
                'description' => 'Mật khẩu',
                'example' => 'password',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'Email',
            'password' => 'Mật khẩu',
        ];
    }
}
