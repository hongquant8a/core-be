<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Strip `projector_image` nếu FE gửi string URL (re-submit) thay vì UploadedFile,
     * để validation `file` không fail.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('projector_image') && ! $this->file('projector_image') instanceof \Illuminate\Http\UploadedFile) {
            $this->offsetUnset('projector_image');
        }
    }

    public function rules(): array
    {
        return [
            'meeting_type_id' => 'nullable|integer|exists:meeting_types,id',
            'meeting_location_id' => 'nullable|integer|exists:meeting_locations,id',
            'chairperson_meeting_attendee_id' => 'nullable|integer|exists:meeting_attendees,id',
            'operator_meeting_attendee_id' => 'nullable|integer|exists:meeting_attendees,id',
            'qr_manager_user_id' => ['nullable', 'integer', 'exists:users,id', $this->qrManagerSameOrgRule()],
            'guests' => 'nullable|array',
            'guests.*.id' => 'nullable|integer|exists:meeting_guests,id',
            'guests.*.name' => 'required_with:guests.*|string|max:255',
            'guests.*.position_name' => 'nullable|string|max:255',
            'guests.*.phone' => 'required_with:guests.*|string|max:30',
            'guests.*.email' => 'required_with:guests.*|email|max:255',
            'guests.*.zalo_user_id' => 'nullable|string|max:64',
            'guests.*.organization_name' => 'nullable|string|max:255',
            'reminders' => 'nullable|array',
            'reminders.*.id' => 'nullable|integer',
            'reminders.*.reminder_type' => 'nullable|string|in:instant,scheduled',
            'reminders.*.moment' => 'nullable|string|in:before,on,after',
            'reminders.*.offset_minutes' => 'nullable|integer|min:0',
            'reminders.*.channels' => 'nullable|array',
            'reminders.*.channels.*' => 'string',
            'projector_image' => 'nullable|file|mimes:jpg,jpeg,png,webp',
            'remove_projector_image' => 'nullable|boolean',
            'title' => 'sometimes|string|max:255',
            'is_public' => 'sometimes|boolean',
            'has_online_room' => 'sometimes|boolean',
            'content' => 'nullable|string',
            'start_time' => 'sometimes|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'attendance_open_at' => 'nullable|date',
            'attendance_close_at' => 'nullable|date|after_or_equal:attendance_open_at',
            'status' => ['sometimes', MeetingStatusEnum::rule()],
            'published_at' => 'nullable|date',
            'allow_host_management' => 'nullable|boolean',
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
                ->where('model_type', (new \App\Modules\Core\Models\User())->getMorphClass())
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
            'guests' => 'Danh sách khách mời',
            'guests.*.name' => 'Họ tên khách mời',
            'guests.*.position_name' => 'Chức vụ khách mời',
            'guests.*.phone' => 'Số điện thoại khách mời',
            'guests.*.email' => 'Email khách mời',
            'guests.*.zalo_user_id' => 'Zalo user ID khách mời',
            'guests.*.organization_name' => 'Đơn vị khách mời',
            'projector_image' => 'Ảnh nền màn chiếu',
            'remove_projector_image' => 'Xóa ảnh nền màn chiếu',
            'title' => 'Tiêu đề',
            'is_public' => 'Trạng thái công khai',
            'has_online_room' => 'Có phòng họp trực tuyến',
            'content' => 'Nội dung',
            'start_time' => 'Thời gian bắt đầu',
            'end_time' => 'Thời gian kết thúc',
            'status' => 'Trạng thái',
            'published_at' => 'Thời gian công khai',
            'allow_host_management' => 'Chủ trì có thể quản lý cuộc họp',
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
                'description' => 'User được giao quyền bật QR điểm danh. Phải cùng tổ chức với meeting. Null = bỏ.',
                'example' => 5,
            ],
            'guests' => [
                'description' => 'Danh sách khách mời (sync). Item có `id` → update existing. Không `id` → tạo mới. Existing không trong list → xóa (nếu invitation chưa sent). Pass [] để xóa hết.',
                'example' => [
                    ['id' => 12, 'name' => 'Nguyễn Văn A', 'phone' => '0901234567', 'email' => 'a@example.com'],
                    ['name' => 'Trần Thị B', 'phone' => '0907654321', 'email' => 'b@example.com'],
                ],
            ],
            'projector_image' => [
                'description' => 'Ảnh nền màn chiếu mới (jpg/png/webp, ≤10MB). Sẽ thay ảnh cũ nếu có.',
            ],
            'remove_projector_image' => [
                'description' => 'Xóa ảnh nền màn chiếu hiện tại (true = xóa, không upload mới).',
                'example' => false,
            ],
            'title' => [
                'description' => 'Tên cuộc họp.',
                'example' => 'Họp tổng kết tháng',
            ],
            'is_public' => [
                'description' => 'Công khai cuộc họp hay không.',
                'example' => true,
            ],
            'has_online_room' => [
                'description' => 'Có phòng họp trực tuyến hay không.',
                'example' => true,
            ],
            'content' => [
                'description' => 'Nội dung cuộc họp.',
                'example' => 'Cập nhật nội dung họp.',
            ],
            'start_time' => [
                'description' => 'Thời gian bắt đầu.',
                'example' => '2026-05-02 08:00:00',
            ],
            'end_time' => [
                'description' => 'Thời gian kết thúc.',
                'example' => '2026-05-02 11:00:00',
            ],
            'status' => [
                'description' => 'Trạng thái cuộc họp.',
                'example' => 'published',
            ],
            'published_at' => [
                'description' => 'Thời gian ban hành.',
                'example' => '2026-05-01 12:00:00',
            ],
            'allow_host_management' => [
                'description' => 'Chủ trì có thể quản lý cuộc họp.',
                'example' => true,
            ],
        ];
    }
}
