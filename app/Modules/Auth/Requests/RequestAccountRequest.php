<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate request mở tài khoản từ guest (gửi tới email liên hệ của hệ thống).
 */
class RequestAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:150',
            'content' => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên không được để trống.',
            'full_name.max' => 'Họ tên tối đa 150 ký tự.',
            'phone.required' => 'Số điện thoại không được để trống.',
            'phone.max' => 'Số điện thoại tối đa 20 ký tự.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'email.max' => 'Email tối đa 150 ký tự.',
            'content.required' => 'Nội dung yêu cầu không được để trống.',
            'content.max' => 'Nội dung yêu cầu tối đa 2000 ký tự.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'full_name' => ['description' => 'Họ và tên của người gửi yêu cầu.', 'example' => 'Nguyễn Văn A'],
            'phone' => ['description' => 'Số điện thoại liên hệ.', 'example' => '0901234567'],
            'email' => ['description' => 'Thư điện tử liên hệ.', 'example' => 'a@example.com'],
            'content' => ['description' => 'Nội dung yêu cầu mở tài khoản.', 'example' => 'Xin mở tài khoản phòng X...'],
        ];
    }
}
