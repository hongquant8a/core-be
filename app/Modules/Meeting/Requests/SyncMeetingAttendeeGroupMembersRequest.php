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
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
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
            'user_ids' => 'Danh sách user',
            'user_ids.*' => 'ID user',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Mảng full list user ID thuộc nhóm sau khi sync. BE auto find-or-create MeetingAttendee cho từng user (1 attendee per org/user) rồi sync pivot. Idempotent.',
                'example' => [10, 11, 12],
            ],
        ];
    }
}
