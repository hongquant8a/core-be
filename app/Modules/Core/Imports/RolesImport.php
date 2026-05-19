<?php

namespace App\Modules\Core\Imports;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RolesImport implements ToModel, WithHeadingRow, WithValidation
{
    use TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên vai trò',
        'guard_name' => 'Guard',
        'organization_id' => 'ID tổ chức',
    ];

    /** Subset xuất ra template — chỉ field required theo StoreRoleRequest. */
    public const TEMPLATE_LABELS = [
        'name' => 'Tên vai trò',
    ];

    public const TEMPLATE_EXAMPLES = [
        'name' => 'Quản trị viên (xóa hàng này trước khi nhập)',
    ];

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

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;
        $data['guard_name'] = isset($data['guard_name']) ? (string) $data['guard_name'] : null;

        return $data;
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
