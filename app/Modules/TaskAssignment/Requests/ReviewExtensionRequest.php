<?php

namespace App\Modules\TaskAssignment\Requests;

class ReviewExtensionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Dùng chung cho `/approve` và `/reject`. Khi từ chối, controller bắt
            // buộc có ghi chú (xem TaskAssignmentItemExtensionController::reject)
            // — người xin cần biết vì sao bị từ chối để còn xin lại cho đúng.
            'review_note' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'review_note.string' => 'Ghi chú phải là chuỗi ký tự.',
            'review_note.max' => 'Ghi chú không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'review_note' => 'Ghi chú của người duyệt',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'review_note' => [
                'description' => 'Ghi chú khi duyệt, hoặc lý do khi từ chối (bắt buộc với từ chối).',
                'example' => 'Đồng ý dời hạn, đề nghị bám sát tiến độ mới.',
            ],
        ];
    }
}
