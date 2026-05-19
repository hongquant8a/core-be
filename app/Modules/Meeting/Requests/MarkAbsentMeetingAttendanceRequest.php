<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkAbsentMeetingAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FE có thể gửi tên field `absence_reason` (đồng bộ với endpoint /respond cho RSVP) HOẶC
     * `note` (chuẩn cũ của attendance). Alias absence_reason → note để cả 2 đều lưu DB.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('note') && $this->filled('absence_reason')) {
            $this->merge(['note' => $this->input('absence_reason')]);
        }
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
            'note' => ['description' => 'Lý do vắng (optional). FE cũng có thể gửi field `absence_reason` cùng ý nghĩa — BE alias về note.', 'example' => 'Bị ốm đột xuất'],
        ];
    }
}
