<?php

namespace App\Modules\Core\Requests;

use App\Modules\Core\Enums\UserStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$this->route('user')?->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'user_name' => 'sometimes|nullable|string|max:100|unique:users,user_name,'.$this->route('user')?->id.'|regex:/^[a-zA-Z0-9._-]*$/',
            'password' => 'sometimes|string|min:6|confirmed',
            'status' => ['sometimes', 'in:'.implode(',', UserStatusEnum::values())],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:5120',
            'assignments' => 'sometimes|array',
            'assignments.*.role_id' => 'required|integer|distinct|exists:roles,id',
            'assignments.*.organization_ids' => 'required|array|min:1',
            'assignments.*.organization_ids.*' => 'integer|distinct|exists:organizations,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Tên người dùng phải là một chuỗi ký tự.',
            'name.max' => 'Tên người dùng không được vượt quá 255 ký tự.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'phone.string' => 'Số điện thoại phải là một chuỗi ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'user_name.unique' => 'Tên đăng nhập :input đã tồn tại.',
            'user_name.string' => 'Tên đăng nhập phải là một chuỗi ký tự.',
            'user_name.max' => 'Tên đăng nhập không được vượt quá 100 ký tự.',
            'user_name.regex' => 'Tên đăng nhập chỉ chấp nhận chữ, số, dấu chấm, gạch dưới, gạch ngang.',
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
                'example' => 'Nguyễn Văn B',
            ],
            'email' => [
                'description' => 'Email đăng nhập',
                'example' => 'user@example.com',
            ],
            'phone' => [
                'description' => 'Số điện thoại',
                'example' => '0901234567',
            ],
            'user_name' => [
                'description' => 'Tên đăng nhập (không dấu cách, cho phép . _ -)',
                'example' => 'nguyenvanb',
            ],
            'password' => [
                'description' => 'Mật khẩu mới (tối thiểu 6 ký tự)',
                'example' => 'newpassword123',
            ],
            'password_confirmation' => [
                'description' => 'Xác nhận mật khẩu mới',
                'example' => 'newpassword123',
            ],
            'status' => [
                'description' => 'Trạng thái người dùng',
                'example' => UserStatusEnum::Active->value,
            ],
            'assignments' => [
                'description' => 'Danh sách gán vai trò theo tổ chức. Khi gửi field này, hệ thống sẽ đồng bộ lại toàn bộ phân quyền theo dữ liệu mới.',
                'example' => [
                    ['role_id' => 1, 'organization_ids' => [2, 3]],
                    ['role_id' => 5, 'organization_ids' => [9]],
                ],
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên người dùng',
            'email' => 'Email',
            'phone' => 'Số điện thoại',
            'user_name' => 'Tên đăng nhập',
            'password' => 'Mật khẩu',
            'status' => 'Trạng thái',
            'avatar' => 'Ảnh đại diện',
            'assignments' => 'Gán vai trò',
        ];
    }
}
