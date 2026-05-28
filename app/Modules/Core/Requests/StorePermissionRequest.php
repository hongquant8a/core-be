<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'guard_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'parent_id' => 'nullable|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên quyền không được để trống.',
            'name.max' => 'Tên quyền không được vượt quá 255 ký tự.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên',
            'guard_name' => 'Guard',
            'description' => 'Mô tả',
            'sort_order' => 'Thứ tự sắp xếp',
            'parent_id' => 'Đơn vị cha',
        ];
    }
}
