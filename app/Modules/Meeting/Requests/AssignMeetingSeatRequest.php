<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignMeetingSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $meetingId = $this->route('meeting')?->id;

        return [
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.seat_id' => ['required', 'integer', 'exists:meeting_seats,id'],
            'assignments.*.meeting_participant_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('meeting_participants', 'id')->where(fn ($query) => $query->where('meeting_id', $meetingId)),
            ],
            'assignments.*.is_vip' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'integer' => ':attribute phải là số nguyên.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
            'array' => ':attribute phải là mảng.',
            'min' => ':attribute phải có ít nhất :min phần tử.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'assignments' => 'Danh sách thay đổi',
            'assignments.*.seat_id' => 'ID ghế',
            'assignments.*.meeting_participant_id' => 'Đại biểu',
            'assignments.*.is_vip' => 'Cờ ghế trưởng đoàn/VIP',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'assignments' => ['description' => 'Danh sách thay đổi theo ghế — mỗi phần tử chỉ cần gửi field muốn đổi.', 'example' => [['seat_id' => 1, 'meeting_participant_id' => 88]]],
            'assignments.*.seat_id' => ['description' => 'ID ghế cần đổi.', 'example' => 1],
            'assignments.*.meeting_participant_id' => ['description' => 'Đại biểu gán vào ghế — null để gỡ. Bỏ qua field này nếu chỉ muốn đổi is_vip.', 'example' => 88],
            'assignments.*.is_vip' => ['description' => 'Đánh dấu/bỏ đánh dấu ghế trưởng đoàn·VIP.', 'example' => true],
        ];
    }
}
