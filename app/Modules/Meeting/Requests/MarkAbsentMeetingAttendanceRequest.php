<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkAbsentMeetingAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // meeting_id lấy từ URL {meeting} qua route binding — không cần body.
        return [
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'string' => ':attribute phải là chuỗi.',
            'max' => ':attribute không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'note' => 'Lý do vắng',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'note' => ['description' => 'Lý do vắng (optional).', 'example' => 'Bị ốm đột xuất'],
        ];
    }
}
