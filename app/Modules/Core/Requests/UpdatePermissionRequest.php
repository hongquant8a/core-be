<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'guard_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'parent_id' => 'nullable|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Tên phải là chuỗi ký tự.',
            'name.max' => 'Tên không được vượt quá 255 ký tự.',
            'guard_name.string' => 'Guard phải là chuỗi ký tự.',
            'guard_name.max' => 'Guard không được vượt quá 255 ký tự.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'description.max' => 'Mô tả không được vượt quá 500 ký tự.',
            'sort_order.integer' => 'Thứ tự sắp xếp phải là số nguyên.',
            'sort_order.min' => 'Thứ tự sắp xếp không được nhỏ hơn 0.',
            'parent_id.exists' => 'Đơn vị cha không tồn tại.',
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
