<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        return [
            // meeting_attendee_group_id: bỏ sau refactor M-N (2026-05-08). Group quản lý qua
            // pivot endpoint /meeting-attendee-groups/{id}/attendees. Field này nếu FE còn
            // gửi → ignore (rule không có nên Laravel strip khỏi validated).
            'user_id' => [
                'required', 'integer', 'exists:users,id',
                Rule::unique('meeting_attendees', 'user_id')->where(fn ($q) => $q->where('organization_id', $orgId)),
            ],
            'position_name' => 'nullable|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'status' => ['required', MeetingCatalogStatusEnum::rule()],
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'in' => ':attribute không hợp lệ.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'unique' => ':attribute đã tồn tại — user này đã là đại biểu trong tổ chức.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Người dùng',
            'position_name' => 'Chức vụ',
            'department_name' => 'Đơn vị',
            'status' => 'Trạng thái',
            'note' => 'Ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_id' => ['description' => 'ID tài khoản trong tổ chức (bắt buộc, unique theo org).', 'example' => 12],
            'position_name' => ['description' => 'Chức vụ override cho ngữ cảnh họp (mặc định lấy từ user).', 'example' => 'Phó chủ tịch'],
            'department_name' => ['description' => 'Đơn vị override cho ngữ cảnh họp.', 'example' => 'UBND phường'],
            'status' => ['description' => 'Trạng thái.', 'example' => 'active'],
            'note' => ['description' => 'Ghi chú nội bộ.', 'example' => 'Đại biểu mời thường xuyên'],
        ];
    }
}
