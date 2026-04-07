<?php

namespace App\Modules\Core\Imports;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $password = $row['password'] ?? 'password';

        return new User([
            'name' => $row['name'] ?? $row['name_'] ?? '',
            'email' => $row['email'] ?? '',
            'user_name' => $row['user_name'] ?? $row['user_name_'] ?? null,
            'password' => Hash::make($password),
            'status' => $row['status'] ?? UserStatusEnum::Active->value,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'user_name' => 'nullable|string|max:100|unique:users,user_name|regex:/^[a-zA-Z0-9._-]*$/',
            'password' => 'nullable|string|min:6',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên người dùng không được để trống.',
            'name.string' => 'Tên người dùng phải là một chuỗi ký tự.',
            'name.max' => 'Tên người dùng không được vượt quá 255 ký tự.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email :input đã tồn tại trong hệ thống.',
            'user_name.string' => 'Tên đăng nhập phải là một chuỗi ký tự.',
            'user_name.max' => 'Tên đăng nhập không được vượt quá 100 ký tự.',
            'user_name.unique' => 'Tên đăng nhập :input đã tồn tại.',
            'user_name.regex' => 'Tên đăng nhập chỉ chấp nhận chữ, số, dấu chấm, gạch dưới, gạch ngang.',
            'password.string' => 'Mật khẩu phải là một chuỗi ký tự.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên người dùng',
            'email' => 'Email',
            'user_name' => 'Tên đăng nhập',
            'password' => 'Mật khẩu',
        ];
    }
}
