<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255|unique:roles,name,' . ($this->route('role')?->id ?? '') . ',id',
            'guard_name' => 'nullable|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
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
            'permission_ids' => 'Danh sách quyền',
            'permission_ids.*' => 'ID quyền',
            'permissions' => 'Danh sách quyền',
            'permissions.*' => 'Tên quyền',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tên vai trò đã tồn tại.',
        ];
    }
}
