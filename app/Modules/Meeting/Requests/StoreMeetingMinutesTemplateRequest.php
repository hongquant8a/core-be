<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingMinutesTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'file' => 'required|file|mimes:docx',
            'is_default' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
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
            'name' => ['description' => 'Tên template biên bản.', 'example' => 'Mẫu biên bản HĐND'],
            'description' => ['description' => 'Mô tả ngắn template.', 'example' => 'Mẫu chuẩn cho kỳ họp HĐND thường lệ.'],
            'file' => ['description' => 'File .docx có placeholder ${var_name}. Xem cheatsheet ở /meeting-minutes-templates/variables.'],
            'is_default' => ['description' => 'Đặt làm template mặc định.', 'example' => true],
            'status' => ['description' => 'Trạng thái: active/inactive.', 'example' => 'active'],
        ];
    }
}
