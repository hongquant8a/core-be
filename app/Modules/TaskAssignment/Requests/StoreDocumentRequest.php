<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class StoreDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string|max:65535',
            'issue_date' => 'nullable|date',
            'task_assignment_type_id' => 'required|integer|exists:task_assignment_types,id',
            'status' => ['required', TaskAssignmentDocumentStatusEnum::rule()],
            'instant_channels' => 'nullable|array',
            'instant_channels.*' => 'string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
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
                'description' => 'Trạng thái văn bản (draft, issued).',
                'example' => 'draft',
            ],
            'attachments' => [
                'description' => 'Danh sách tệp đính kèm. Có thể truyền file mới (multipart/form-data) hoặc truyền chuỗi JSON/object của file cũ để giữ lại. Tối đa 10 tệp, mỗi tệp 20MB.',
                'example' => [],
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên',
            'summary' => 'Summary',
            'issue_date' => 'Ngày ban hành',
            'task_assignment_type_id' => 'Task assignment type',
            'status' => 'Trạng thái',
            'attachments' => 'Tệp đính kèm',
            'attachments.*' => 'Tệp đính kèm',
        ];
    }
}
