<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class UpdateDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'summary' => 'nullable|string|max:65535',
            'issue_date' => 'nullable|date',
            'task_assignment_type_id' => 'nullable|integer|exists:task_assignment_types,id',
            'status' => ['sometimes', TaskAssignmentDocumentStatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tên văn bản giao việc.',
                'example' => 'Văn bản giao việc tháng 4/2026',
            ],
            'summary' => [
                'description' => 'Tóm tắt nội dung văn bản.',
                'example' => 'Văn bản triển khai công việc quý II.',
            ],
            'issue_date' => [
                'description' => 'Ngày ban hành văn bản.',
                'example' => '2026-04-01',
            ],
            'task_assignment_type_id' => [
                'description' => 'ID loại giao việc.',
                'example' => 1,
            ],
            'status' => [
                'description' => 'Trạng thái văn bản.',
                'example' => TaskAssignmentDocumentStatusEnum::Issued->value,
            ],
        ];
    }
}
