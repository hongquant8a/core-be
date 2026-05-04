<?php

namespace App\Modules\Meeting\Imports;

use App\Modules\Core\Traits\TranslatesExcelHeadings;
use App\Modules\Meeting\Models\MeetingAttendee;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MeetingAttendeeImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Họ tên',
        'position_name' => 'Chức vụ',
        'department_name' => 'Đơn vị',
        'email' => 'Email',
        'phone' => 'Số điện thoại',
        'status' => 'Trạng thái',
        'note' => 'Ghi chú',
    ];

    public const TEMPLATE_LABELS = [
        'name' => 'Họ tên',
        'status' => 'Trạng thái',
    ];

    public function model(array $row)
    {
        return new MeetingAttendee([
            'name' => $row['name'] ?? null,
            'position_name' => $row['position_name'] ?? null,
            'department_name' => $row['department_name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => isset($row['phone']) ? (string) $row['phone'] : null,
            'status' => $row['status'] ?? 'active',
            'note' => $row['note'] ?? null,
            'organization_id' => function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null,
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);
        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Họ tên không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Họ tên',
            'email' => 'Email',
            'phone' => 'Số điện thoại',
            'status' => 'Trạng thái',
        ];
    }
}
