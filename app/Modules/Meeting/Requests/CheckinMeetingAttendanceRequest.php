<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckinMeetingAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'meeting_id' => 'ID cuộc họp',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'meeting_id' => ['description' => 'ID cuộc họp đại biểu điểm danh.', 'example' => 1],
        ];
    }
}
