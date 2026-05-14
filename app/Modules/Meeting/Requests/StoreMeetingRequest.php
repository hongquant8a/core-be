<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_type_id' => 'nullable|integer|exists:meeting_types,id',
            'meeting_location_id' => 'nullable|integer|exists:meeting_locations,id',
            'chairperson_meeting_attendee_id' => 'nullable|integer|exists:meeting_attendees,id',
            'operator_meeting_attendee_id' => 'nullable|integer|exists:meeting_attendees,id',
            'qr_manager_user_id' => ['nullable', 'integer', 'exists:users,id', $this->qrManagerSameOrgRule()],
            'title' => 'required|string|max:255',
            'is_public' => 'nullable|boolean',
            'content' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'attendance_open_at' => 'nullable|date',
            'attendance_close_at' => 'nullable|date|after_or_equal:attendance_open_at',
            'status' => ['nullable', MeetingStatusEnum::rule()],
            'published_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'numeric' => ':attribute phải là số.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
            'array' => ':attribute phải là mảng.',
            'file' => ':attribute phải là tệp hợp lệ.',
            'mimes' => ':attribute phải đúng định dạng tệp cho phép.',
            'max' => ':attribute không được vượt quá :max ký tự/phần tử/dung lượng.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'date' => ':attribute phải là ngày hợp lệ.',
            'after_or_equal' => ':attribute phải sau hoặc bằng :date.',
            'in' => ':attribute không hợp lệ.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'unique' => ':attribute đã tồn tại.',
        ];
    }
    /**
     * Custom rule: qr_manager_user_id phải thuộc cùng tổ chức hiện tại (qua model_has_roles).
     */
    protected function qrManagerSameOrgRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            if (! $value) {
                return;
            }
            $orgId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
            if (! $orgId) {
                return;
            }
            $hasAccess = \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->where('model_id', $value)
                ->where('model_type', \App\Modules\Core\Models\User::class)
                ->where('organization_id', $orgId)
                ->exists();
            if (! $hasAccess) {
                $fail('Người quản lý QR phải thuộc tổ chức hiện tại.');
            }
        };
    }

    public function attributes(): array
    {
        return [
            'meeting_type_id' => 'ID loại cuộc họp',
            'meeting_location_id' => 'ID địa điểm họp',
            'qr_manager_user_id' => 'Người quản lý QR điểm danh',
            'title' => 'Tiêu đề',
            'is_public' => 'Trạng thái công khai',
            'content' => 'Nội dung',
            'start_time' => 'Thời gian bắt đầu',
            'end_time' => 'Thời gian kết thúc',
            'attendance_open_at' => 'Thời gian mở điểm danh',
            'attendance_close_at' => 'Thời gian đóng điểm danh',
            'status' => 'Trạng thái',
            'published_at' => 'Thời gian công khai',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_type_id' => [
                'description' => 'ID loại cuộc họp.',
                'example' => 1,
            ],
            'meeting_location_id' => [
                'description' => 'ID địa điểm họp.',
                'example' => 1,
            ],
            'qr_manager_user_id' => [
                'description' => 'User được giao quyền bật QR điểm danh. Phải cùng tổ chức với meeting. Nếu null → chỉ chair/op có quyền QR.',
                'example' => 5,
            ],
            'title' => [
                'description' => 'Tên cuộc họp.',
                'example' => 'Họp giao ban tuần',
            ],
            'is_public' => [
                'description' => 'Công khai cuộc họp hay không.',
                'example' => false,
            ],
            'content' => [
                'description' => 'Nội dung cuộc họp.',
                'example' => 'Nội dung chuẩn bị họp.',
            ],
            'start_time' => [
                'description' => 'Thời gian bắt đầu.',
                'example' => '2026-05-01 08:00:00',
            ],
            'end_time' => [
                'description' => 'Thời gian kết thúc.',
                'example' => '2026-05-01 10:00:00',
            ],
            'attendance_open_at' => [
                'description' => 'Thời điểm mở điểm danh (đại biểu chỉ bấm điểm danh được trong khoảng này). Null = không giới hạn.',
                'example' => '2026-05-01 07:30:00',
            ],
            'attendance_close_at' => [
                'description' => 'Thời điểm đóng điểm danh. Null = không giới hạn.',
                'example' => '2026-05-01 08:15:00',
            ],
            'status' => [
                'description' => 'Trạng thái cuộc họp.',
                'example' => 'draft',
            ],
            'published_at' => [
                'description' => 'Thời gian ban hành (nếu có).',
                'example' => null,
            ],
        ];
    }
}
