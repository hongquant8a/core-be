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
            'meeting_attendee_group_id' => 'nullable|integer|exists:meeting_attendee_groups,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'position_name' => 'nullable|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => ['sometimes', MeetingCatalogStatusEnum::rule()],
            'note' => 'nullable|string',
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
    public function attributes(): array
    {
        return [
            'meeting_attendee_group_id' => 'ID nhóm đại biểu',
            'user_id' => 'ID user',
            'name' => 'Tên',
            'position_name' => 'position name',
            'department_name' => 'department name',
            'email' => 'Email',
            'phone' => 'Số điện thoại',
            'status' => 'Trạng thái',
            'note' => 'Ghi chú',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_attendee_group_id' => ['description' => 'ID nhóm đại biểu.', 'example' => 1],
            'user_id' => ['description' => 'ID tài khoản hệ thống (nếu có).', 'example' => 12],
            'name' => ['description' => 'Họ tên đại biểu.', 'example' => 'Trần Thị B'],
            'status' => ['description' => 'Trạng thái đại biểu.', 'example' => 'inactive'],
            'note' => ['description' => 'Ghi chú.', 'example' => 'Tạm ngưng tham gia'],
        ];
    }
}
