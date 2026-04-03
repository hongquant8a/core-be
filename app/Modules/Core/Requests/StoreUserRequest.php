<?php

namespace App\Modules\Core\Requests;

use App\Modules\Core\Enums\UserStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('user_name') && trim((string) $this->user_name) === '') {
            $this->merge(['user_name' => null]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'user_name' => 'nullable|string|max:100|unique:users,user_name|regex:/^[a-zA-Z0-9._-]*$/',
            'password' => 'required|string|min:6|confirmed',
            'status' => ['nullable', 'in:'.implode(',', UserStatusEnum::values())],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:5120',
            'assignments' => 'nullable|array',
            'assignments.*.role_id' => 'required|integer|distinct|exists:roles,id',
            'assignments.*.organization_ids' => 'required|array|min:1',
            'assignments.*.organization_ids.*' => 'integer|distinct|exists:organizations,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên người dùng không được để trống.',
            'name.string' => 'Tên người dùng phải là một chuỗi ký tự.',
            'name.max' => 'Tên người dùng không được vượt quá 255 ký tự.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'user_name.unique' => 'Tên đăng nhập đã tồn tại.',
            'user_name.regex' => 'Tên đăng nhập chỉ chấp nhận chữ, số, dấu chấm, gạch dưới, gạch ngang.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.string' => 'Mật khẩu phải là một chuỗi ký tự.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Mật khẩu không khớp.',
            'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận active, inactive, banned.',
            'assignments.array' => 'Danh sách phân quyền phải là mảng.',
            'assignments.*.role_id.required' => 'Vai trò là bắt buộc trong từng phân quyền.',
            'assignments.*.role_id.integer' => 'ID vai trò phải là số nguyên.',
            'assignments.*.role_id.distinct' => 'Vai trò bị trùng trong danh sách phân quyền.',
            'assignments.*.role_id.exists' => 'Vai trò không tồn tại.',
            'assignments.*.organization_ids.required' => 'Tổ chức là bắt buộc trong từng phân quyền.',
            'assignments.*.organization_ids.array' => 'Danh sách tổ chức phải là mảng.',
            'assignments.*.organization_ids.min' => 'Mỗi vai trò phải có ít nhất một tổ chức.',
            'assignments.*.organization_ids.*.integer' => 'ID tổ chức phải là số nguyên.',
            'assignments.*.organization_ids.*.distinct' => 'Tổ chức bị trùng trong cùng một vai trò.',
            'assignments.*.organization_ids.*.exists' => 'Tổ chức không tồn tại.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tên người dùng',
                'example' => 'Nguyễn Văn A',
            ],
            'email' => [
                'description' => 'Email đăng nhập',
                'example' => 'user@example.com',
            ],
            'user_name' => [
                'description' => 'Tên đăng nhập (không dấu cách, cho phép . _ -)',
                'example' => 'nguyenvana',
            ],
            'password' => [
                'description' => 'Mật khẩu (tối thiểu 6 ký tự)',
                'example' => 'password123',
            ],
            'password_confirmation' => [
                'description' => 'Xác nhận mật khẩu',
                'example' => 'password123',
            ],
            'status' => [
                'description' => 'Trạng thái người dùng',
                'example' => UserStatusEnum::Active->value,
            ],
            'assignments' => [
                'description' => 'Danh sách gán vai trò theo tổ chức. Mỗi phần tử gồm role_id và organization_ids.',
                'example' => [
                    ['role_id' => 1, 'organization_ids' => [2, 3]],
                    ['role_id' => 5, 'organization_ids' => [9]],
                ],
            ],
        ];
    }
}
