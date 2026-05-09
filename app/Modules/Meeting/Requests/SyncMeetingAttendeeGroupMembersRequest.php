<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMeetingAttendeeGroupMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_attendee_ids' => ['required', 'array'],
            'meeting_attendee_ids.*' => ['integer', 'exists:meeting_attendees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'array' => ':attribute phải là mảng.',
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'meeting_attendee_ids' => 'Danh sách đại biểu',
            'meeting_attendee_ids.*' => 'ID đại biểu',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'meeting_attendee_ids' => [
                'description' => 'Mảng full list ID đại biểu thuộc nhóm sau khi sync. BE diff với current pivot → thêm/xoá pivot rows. Idempotent.',
                'example' => [5, 6, 7],
            ],
        ];
    }
}
