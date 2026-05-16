<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingPersonalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nested route `/meetings/{meeting}/personal-notes` — auto-inject meeting_id từ URL
     * để FE không cần gửi field thừa. Flat route vẫn yêu cầu FE gửi body (route('meeting') null).
     */
    protected function prepareForValidation(): void
    {
        $route = $this->route('meeting');
        if ($route && ! $this->filled('meeting_id')) {
            $meetingId = is_object($route) ? ($route->id ?? null) : $route;
            if ($meetingId !== null) {
                $this->merge(['meeting_id' => $meetingId]);
            }
        }
    }

    public function rules(): array
    {
        // meeting_participant_id auto-derive từ auth user trong service (không nhận từ FE
        // để tránh user tạo note hộ người khác).
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'content' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
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
            'content' => 'Nội dung',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_id' => ['description' => 'ID cuộc họp.', 'example' => 1],
            'content' => ['description' => 'Nội dung ghi chú cá nhân.', 'example' => 'Ghi chú nội dung cần theo dõi'],
            'sort_order' => ['description' => 'Thứ tự ghi chú.', 'example' => 1],
        ];
    }
}
