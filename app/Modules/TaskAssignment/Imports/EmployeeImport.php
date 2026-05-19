<?php

namespace App\Modules\TaskAssignment\Imports;

use App\Modules\Core\Traits\TranslatesExcelHeadings;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployee;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EmployeeImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'user_id' => 'ID user',
        'status' => 'Trạng thái',
        'note' => 'Ghi chú',
    ];

    /** Subset xuất ra template — required + optional thường dùng. */
    public const TEMPLATE_LABELS = [
        'user_id' => 'ID user',
        'status' => 'Trạng thái',
        'note' => 'Ghi chú',
    ];

    public function model(array $row)
    {
        return new TaskAssignmentEmployee([
            'user_id' => $row['user_id'] ?? null,
            'status' => $row['status'] ?? 'active',
            'note' => $row['note'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['user_id'] = isset($data['user_id']) ? (int) $data['user_id'] : null;

        return $data;
    }

    public function rules(): array
    {
        $orgId = getPermissionsTeamId();

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                \Illuminate\Validation\Rule::unique('task_assignment_employees', 'user_id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId)),
            ],
            'status' => 'nullable|in:active,inactive',
            'note' => 'nullable|string|max:65535',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'user_id.required' => 'ID user không được để trống.',
            'user_id.integer' => 'ID user phải là số nguyên.',
            'user_id.exists' => 'ID user :input không tồn tại trong hệ thống.',
            'user_id.unique' => 'ID user :input đã là nhân viên của module trong tổ chức hiện tại.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
            'note.max' => 'Ghi chú quá dài.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'user_id' => 'ID user',
            'status' => 'Trạng thái',
            'note' => 'Ghi chú',
        ];
    }
}
