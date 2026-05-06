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
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'max' => ':attribute không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'meeting_id' => 'ID cuộc họp',
            'note' => 'Lý do vắng',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'meeting_id' => ['description' => 'ID cuộc họp đại biểu báo vắng.', 'example' => 1],
            'note' => ['description' => 'Lý do vắng (optional).', 'example' => 'Bị ốm đột xuất'],
        ];
    }
}
