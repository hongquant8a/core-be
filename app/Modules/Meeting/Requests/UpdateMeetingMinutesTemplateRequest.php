<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingMinutesTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:500',
            'file' => 'sometimes|file|mimes:docx|max:10240',
            'is_default' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'string' => ':attribute phải là chuỗi.',
            'max' => ':attribute không được vượt quá :max ký tự/dung lượng.',
            'file' => ':attribute phải là tệp hợp lệ.',
            'mimes' => ':attribute phải có định dạng .docx.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên template',
            'description' => 'Mô tả',
            'file' => 'Tệp template (.docx)',
            'is_default' => 'Mặc định',
            'status' => 'Trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tên template.', 'example' => 'Mẫu biên bản HĐND'],
            'description' => ['description' => 'Mô tả ngắn.', 'example' => 'Cập nhật mẫu biên bản.'],
            'file' => ['description' => 'File .docx mới (thay thế file cũ).'],
            'is_default' => ['description' => 'Đặt làm template mặc định.', 'example' => true],
            'status' => ['description' => 'Trạng thái: active/inactive.', 'example' => 'active'],
        ];
    }
}
