<?php

namespace App\Modules\Core\Imports;

use App\Modules\Core\Models\Role;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RolesImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $guard = $row['guard_name'] ?? config('auth.defaults.guard', 'web');
        $organizationId = isset($row['organization_id']) ? (int) $row['organization_id'] : null;

        return new Role([
            'name' => $row['name'] ?? $row['name_'] ?? '',
            'guard_name' => $guard,
            'organization_id' => $organizationId ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'guard_name' => 'nullable|string|max:255',
            'organization_id' => 'nullable|integer|exists:organizations,id',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên vai trò không được để trống.',
            'name.string' => 'Tên vai trò phải là một chuỗi ký tự.',
            'organization_id.exists' => 'ID tổ chức không tồn tại.',
            'organization_id.integer' => 'ID tổ chức phải là số nguyên.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên vai trò',
            'guard_name' => 'Guard name',
            'organization_id' => 'Tổ chức',
        ];
    }
}
