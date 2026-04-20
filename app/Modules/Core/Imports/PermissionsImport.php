<?php

namespace App\Modules\Core\Imports;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PermissionsImport implements ToModel, WithHeadingRow, WithValidation
{
    use TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên quyền',
        'guard_name' => 'Guard',
        'description' => 'Mô tả',
        'sort_order' => 'Thứ tự',
        'parent_id' => 'ID quyền cha',
    ];

    /** Subset xuất ra template — chỉ field required theo StorePermissionRequest. */
    public const TEMPLATE_LABELS = [
        'name' => 'Tên quyền',
    ];

    public function model(array $row)
    {
        $guard = $row['guard_name'] ?? config('auth.defaults.guard', 'web');
        $parentId = isset($row['parent_id']) && $row['parent_id'] !== '' ? (int) $row['parent_id'] : null;

        return new Permission([
            'name' => $row['name'] ?? $row['name_'] ?? '',
            'guard_name' => $guard,
            'description' => $row['description'] ?? null,
            'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
            'parent_id' => $parentId,
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
            'parent_id' => 'nullable|integer|exists:permissions,id',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên quyền không được để trống.',
            'name.string' => 'Tên quyền phải là một chuỗi ký tự.',
            'parent_id.exists' => 'ID quyền cha không tồn tại.',
            'parent_id.integer' => 'ID quyền cha phải là số nguyên.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên quyền',
            'guard_name' => 'Guard name',
            'parent_id' => 'Quyền cha',
            'description' => 'Mô tả',
            'sort_order' => 'Thứ tự',
        ];
    }
}
