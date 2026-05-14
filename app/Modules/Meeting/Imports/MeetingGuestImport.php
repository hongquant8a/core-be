<?php

namespace App\Modules\Meeting\Imports;

use App\Modules\Core\Traits\TranslatesExcelHeadings;
use App\Modules\Meeting\Models\MeetingGuest;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MeetingGuestImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Họ tên',
        'email' => 'Email',
        'phone' => 'Số điện thoại',
        'note' => 'Ghi chú',
        'status' => 'Trạng thái',
    ];

    public const TEMPLATE_LABELS = [
        'name' => 'Họ tên',
        'email' => 'Email',
        'phone' => 'Số điện thoại',
        'note' => 'Ghi chú',
    ];

    public function model(array $row)
    {
        return new MeetingGuest([
            'name' => $row['name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'note' => $row['note'] ?? null,
            'status' => $row['status'] ?? 'active',
            'organization_id' => function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null,
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        return $this->translateHeadings($data);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'note' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Họ tên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'phone.required' => 'Số điện thoại không được để trống.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Họ tên',
            'email' => 'Email',
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
            'status' => 'Trạng thái',
        ];
    }
}
