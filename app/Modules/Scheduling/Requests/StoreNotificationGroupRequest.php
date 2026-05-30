<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'array' => ':attribute phải là mảng.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên nhóm thông báo',
            'description' => 'Mô tả',
            'user_ids' => 'Danh sách thành viên',
            'user_ids.*' => 'ID người dùng',
        ];
    }
}
