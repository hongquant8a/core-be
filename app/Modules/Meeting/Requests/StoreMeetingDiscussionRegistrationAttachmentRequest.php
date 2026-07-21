<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingDiscussionRegistrationAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nested route `/meetings/{m}/discussion-registrations/{meetingDiscussionRegistration}/attachments` —
     * auto-inject meeting_discussion_registration_id từ URL, FE chỉ gửi { file, file_name?, sort_order? }.
     */
    protected function prepareForValidation(): void
    {
        $route = $this->route('meetingDiscussionRegistration');
        if ($route && ! $this->filled('meeting_discussion_registration_id')) {
            $regId = is_object($route) ? ($route->id ?? null) : $route;
            if ($regId !== null) {
                $this->merge(['meeting_discussion_registration_id' => $regId]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'meeting_discussion_registration_id' => 'required|integer|exists:meeting_discussion_registrations,id',
            'file' => 'required|file',
            'file_name' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'file' => ':attribute phải là tệp hợp lệ.',
            'max' => ':attribute không được vượt quá :max.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'meeting_discussion_registration_id' => 'ID đăng ký thảo luận/chất vấn',
            'file' => 'Tệp tải lên',
            'file_name' => 'Tên tập tin',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'meeting_discussion_registration_id' => ['description' => 'ID đăng ký (auto-inject từ URL).', 'example' => 5],
            'file' => ['description' => 'Tệp đính kèm (≤10MB).'],
            'file_name' => ['description' => 'Tên hiển thị do user đặt. Bỏ trống → dùng tên gốc file.', 'example' => 'Slide trình chiếu'],
            'sort_order' => ['description' => 'Thứ tự hiển thị file.', 'example' => 1],
        ];
    }
}
