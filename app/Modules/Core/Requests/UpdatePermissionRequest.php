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
