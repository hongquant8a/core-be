<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'prohibited',
            'meeting_attendee_group_id' => 'nullable|integer|exists:meeting_attendee_groups,id',
            'position_name' => 'nullable|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'status' => ['sometimes', MeetingCatalogStatusEnum::rule()],
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'in' => ':attribute không hợp lệ.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'user_id.prohibited' => 'Không được đổi người dùng của đại biểu — tạo mới attendee thay vì cập nhật.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Người dùng',
            'meeting_attendee_group_id' => 'Nhóm đại biểu',
            'position_name' => 'Chức vụ',
            'department_name' => 'Đơn vị',
            'status' => 'Trạng thái',
            'note' => 'Ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'meeting_attendee_group_id' => ['description' => 'ID nhóm đại biểu.', 'example' => 1],
            'position_name' => ['description' => 'Chức vụ override.', 'example' => 'Trưởng ban'],
            'department_name' => ['description' => 'Đơn vị override.', 'example' => 'UBND quận'],
            'status' => ['description' => 'Trạng thái.', 'example' => 'inactive'],
            'note' => ['description' => 'Ghi chú.', 'example' => 'Tạm ngưng tham gia'],
        ];
    }
}
