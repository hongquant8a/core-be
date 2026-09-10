<?php

namespace App\Modules\TaskAssignment\Requests;

class AnalyzeDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // max 50.000 ký tự: văn bản 8.4KB đã sinh 45 đầu việc (~12KB JSON) và
            // chạm trần 8192 token output — dài hơn nữa thì kết quả bị cắt.
            'content' => 'required|string|min:20|max:50000',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Nội dung văn bản không được để trống.',
            'content.string' => 'Nội dung văn bản phải là chuỗi ký tự.',
            'content.min' => 'Nội dung văn bản phải có ít nhất :min ký tự.',
            'content.max' => 'Nội dung văn bản không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'content' => 'Nội dung văn bản',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'content' => [
                'description' => 'Nội dung văn bản thô cần phân tích (dán từ file hoặc gõ tay).',
                'example' => 'THÔNG BÁO Kết luận của đồng chí Bí thư tại cuộc họp giao ban tháng 9...',
            ],
        ];
    }
}
