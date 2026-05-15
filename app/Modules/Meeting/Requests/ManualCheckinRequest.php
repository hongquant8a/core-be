<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingAttendanceStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // meeting_id lấy từ URL {meeting} qua route binding — không cần body.
        return [
            'meeting_participant_id' => ['required', 'integer', 'exists:meeting_participants,id'],
            'status' => ['required', Rule::in([
                MeetingAttendanceStatusEnum::Present->value,
                MeetingAttendanceStatusEnum::Absent->value,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'integer' => ':attribute phải là số nguyên.',
            'string' => ':attribute phải là chuỗi.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'in' => ':attribute không hợp lệ — chỉ chấp nhận present/absent.',
        ];
    }

    public function attributes(): array
    {
        return [
            'meeting_participant_id' => 'ID đại biểu',
            'status' => 'Trạng thái điểm danh',
            'note' => 'Ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'meeting_participant_id' => ['description' => 'ID đại biểu được điểm danh hộ.', 'example' => 12],
            'status' => ['description' => 'Trạng thái: present (có mặt) | absent (vắng).', 'example' => 'present'],
            'note' => ['description' => 'Ghi chú lý do (nếu vắng).', 'example' => 'Bị ốm đột xuất'],
        ];
    }
}
