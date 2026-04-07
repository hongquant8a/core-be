<?php

namespace App\Modules\TaskAssignment\Imports;

use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DepartmentImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

    public function model(array $row)
    {
        return new TaskAssignmentDepartment([
            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'description' => $row['description'] ?? null,
            'status' => $row['status'] ?? 'active',
            'sort_order' => $row['sort_order'] ?? 0,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:task_assignment_departments,code',
            'name' => 'required|string|max:255',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'code.required' => 'Mã phòng ban không được để trống.',
            'code.string' => 'Mã phòng ban phải là một chuỗi ký tự.',
            'code.max' => 'Mã phòng ban không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã phòng ban :input đã tồn tại.',
            'name.required' => 'Tên phòng ban không được để trống.',
            'name.string' => 'Tên phòng ban phải là một chuỗi ký tự.',
            'name.max' => 'Tên phòng ban không được vượt quá 255 ký tự.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'code' => 'Mã phòng ban',
            'name' => 'Tên phòng ban',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự',
        ];
    }
}
