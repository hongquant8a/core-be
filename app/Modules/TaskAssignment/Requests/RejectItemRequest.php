<?php

namespace App\Modules\TaskAssignment\Requests;

class RejectItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|max:5000',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'rejection_reason' => [
                'description' => 'Lý do từ chối duyệt hoàn thành.',
                'example' => 'Báo cáo chưa đầy đủ, cần bổ sung tài liệu.',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'rejection_reason' => 'Lý do từ chối',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Lý do từ chối là bắt buộc.',
            'rejection_reason.string' => 'Lý do từ chối phải là chuỗi.',
            'rejection_reason.max' => 'Lý do từ chối không được vượt quá :max ký tự.',
        ];
    }
}
