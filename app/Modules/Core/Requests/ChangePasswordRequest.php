<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate request đổi mật khẩu của chính tài khoản đang đăng nhập.
 *
 * Khác ResetPasswordRequest (quên mật khẩu — xác thực bằng token gửi email), luồng này
 * người dùng đã đăng nhập nên phải chứng minh quyền sở hữu bằng mật khẩu hiện tại:
 * chỉ có Bearer token là chưa đủ (token bị lộ → chiếm tài khoản vĩnh viễn).
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // guard 'api' (sanctum) — chỉ định tường minh để không phụ thuộc env AUTH_GUARD.
            'current_password' => ['required', 'string', 'current_password:api'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
            'password_confirmation.required' => 'Vui lòng nhập lại mật khẩu mới.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'current_password' => [
                'description' => 'Mật khẩu hiện tại.',
                'example' => 'password123',
            ],
            'password' => [
                'description' => 'Mật khẩu mới (tối thiểu 6 ký tự, khác mật khẩu hiện tại).',
                'example' => 'newpassword123',
            ],
            'password_confirmation' => [
                'description' => 'Nhập lại mật khẩu mới.',
                'example' => 'newpassword123',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'Mật khẩu hiện tại',
            'password' => 'Mật khẩu mới',
            'password_confirmation' => 'Xác nhận mật khẩu mới',
        ];
    }
}
