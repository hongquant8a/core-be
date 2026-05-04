<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingParticipantResponseStatusEnum;
use App\Modules\Meeting\Enums\MeetingParticipantRoleEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', MeetingParticipantRoleEnum::rule()],
            'response_status' => ['sometimes', MeetingParticipantResponseStatusEnum::rule()],
            'absence_reason' => 'nullable|string',
            'responded_at' => 'nullable|date',
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
            'role' => 'role',
            'response_status' => 'Trạng thái phản hồi',
            'absence_reason' => 'absence reason',
            'responded_at' => 'responded at',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'role' => ['description' => 'Vai trò người tham dự.', 'example' => 'operator'],
            'response_status' => ['description' => 'Trạng thái phản hồi.', 'example' => 'accepted'],
            'absence_reason' => ['description' => 'Lý do vắng (nếu có).', 'example' => 'Bận công tác'],
            'responded_at' => ['description' => 'Thời điểm phản hồi.', 'example' => '2026-05-01 07:30:00'],
        ];
    }
}
