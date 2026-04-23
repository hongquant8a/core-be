<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class UpdateDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'summary' => 'sometimes|nullable|string|max:65535',
            'issue_date' => 'sometimes|nullable|date',
            'task_assignment_type_id' => 'nullable|integer|exists:task_assignment_types,id',
            'status' => ['sometimes', TaskAssignmentDocumentStatusEnum::rule()],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
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
                'description' => 'Ngày ban hành (Y-m-d).',
                'example' => '2026-04-01',
            ],
            'task_assignment_type_id' => [
                'description' => 'ID loại văn bản giao việc.',
                'example' => 1,
            ],
            'status' => [
                'description' => 'Trạng thái văn bản (draft, issued, published, revoked).',
                'example' => 'issued',
            ],
            'attachments' => [
                'description' => 'Danh sách tệp đính kèm. Có thể truyền file mới (multipart/form-data) hoặc truyền chuỗi JSON/object của file cũ để giữ lại. Tối đa 10 tệp, mỗi tệp 20MB.',
                'example' => [],
            ],
            'remove_attachment_ids' => [
                'description' => 'Danh sách ID tệp đính kèm cần xóa.',
                'example' => [1, 2],
            ],
        ];
    }
}
