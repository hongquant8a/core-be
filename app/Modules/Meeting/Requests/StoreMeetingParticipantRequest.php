<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingParticipantResponseStatusEnum;
use App\Modules\Meeting\Enums\MeetingParticipantRoleEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'meeting_attendee_id' => 'required|integer|exists:meeting_attendees,id',
            'role' => ['nullable', MeetingParticipantRoleEnum::rule()],
            'response_status' => ['nullable', MeetingParticipantResponseStatusEnum::rule()],
            'absence_reason' => 'nullable|string',
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
            'meeting_id' => 'ID cuộc họp',
            'meeting_attendee_id' => 'ID đại biểu',
            'role' => 'role',
            'response_status' => 'Trạng thái phản hồi',
            'absence_reason' => 'absence reason',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_id' => [
                'description' => 'ID cuộc họp.',
                'example' => 1,
            ],
            'meeting_attendee_id' => [
                'description' => 'ID đại biểu trong danh bạ meeting.',
                'example' => 1,
            ],
            'role' => [
                'description' => 'Vai trò tham dự.',
                'example' => 'delegate',
            ],
            'response_status' => [
                'description' => 'Trạng thái phản hồi tham dự.',
                'example' => 'pending',
            ],
            'absence_reason' => [
                'description' => 'Lý do vắng (nếu có).',
                'example' => 'Đi công tác đột xuất',
            ],
        ];
    }
}
