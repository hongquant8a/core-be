<?php

namespace App\Modules\Meeting\Imports;

use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use App\Modules\Meeting\Models\MeetingAttendee;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import đại biểu — match user theo email. Row không tìm thấy user → skip (validation fail).
 */
class MeetingAttendeeImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'email' => 'Email',
        'position_name' => 'Chức vụ',
        'department_name' => 'Đơn vị',
        'status' => 'Trạng thái',
        'note' => 'Ghi chú',
    ];

    public const TEMPLATE_LABELS = [
        'email' => 'Email',
        'position_name' => 'Chức vụ',
        'department_name' => 'Đơn vị',
        'note' => 'Ghi chú',
    ];

    public const TEMPLATE_EXAMPLES = [
        'email' => 'daibieu@example.com',
        'position_name' => 'Đại biểu HĐND',
        'department_name' => 'Phòng Hành chính (xóa hàng này)',
        'note' => 'Ví dụ mẫu',
    ];

    public function model(array $row)
    {
        $user = User::where('email', $row['email'])->first();
        if (! $user) {
            return null; // skip — validation đã đảm bảo email exists, nhưng phòng race
        }

        $orgId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        // Skip nếu attendee đã tồn tại (unique org+user_id sẽ throw nếu không kiểm)
        if (MeetingAttendee::where('organization_id', $orgId)->where('user_id', $user->id)->exists()) {
            return null;
        }

        return new MeetingAttendee([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'position_name' => $row['position_name'] ?? null,
            'department_name' => $row['department_name'] ?? null,
            'status' => $row['status'] ?? 'active',
            'note' => $row['note'] ?? null,
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);
        $data['email'] = isset($data['email']) ? (string) $data['email'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.exists' => 'Không tìm thấy user với email này.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'email' => 'Email',
            'status' => 'Trạng thái',
        ];
    }
}
