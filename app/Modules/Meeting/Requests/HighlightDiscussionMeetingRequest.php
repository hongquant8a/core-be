<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HighlightDiscussionMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discussion_registration_id' => ['nullable', 'integer', 'exists:meeting_discussion_registrations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'discussion_registration_id' => 'Đăng ký phát biểu',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'discussion_registration_id' => [
                'description' => 'ID đăng ký phát biểu/chất vấn cần highlight lên màn chiếu. Null để bỏ highlight.',
                'example' => 12,
            ],
        ];
    }
}
