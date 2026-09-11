<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class UpdateDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Xem chú thích ở StoreDocumentRequest: cột `name` là TEXT, 20000 ký
            // tự là trần an toàn khi mỗi ký tự tiếng Việt tốn tới 3 byte.
            'name' => 'sometimes|string|max:20000',
            'summary' => 'sometimes|nullable|string|max:65535',
            // Xem chú thích ở StoreDocumentRequest.
            'ai_source_content' => 'sometimes|nullable|string|max:50000',
            'issue_date' => 'sometimes|nullable|date',
            'task_assignment_type_id' => 'nullable|integer|exists:task_assignment_types,id',
            'status' => ['sometimes', TaskAssignmentDocumentStatusEnum::rule()],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Tên văn bản không được vượt quá 255 ký tự.',
            'ai_source_content.max' => 'Nội dung văn bản gốc không được vượt quá :max ký tự.',
            'issue_date.date' => 'Ngày ban hành không đúng định dạng.',
            'task_assignment_type_id.exists' => 'Loại văn bản không tồn tại.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'attachments.max' => 'Tối đa 10 tệp đính kèm.',
            'remove_attachment_ids.*.integer' => 'ID tệp xóa phải là số nguyên.',
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
            'ai_source_content' => [
                'description' => 'Nguyên văn đã dán vào ô phân tích AI (nếu có).',
                'example' => 'THÔNG BÁO Kết luận của đồng chí Bí thư tại cuộc họp giao ban tháng 9...',
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

    public function attributes(): array
    {
        return [
            'name' => 'Tên',
            'summary' => 'Summary',
            'ai_source_content' => 'Nội dung văn bản gốc',
            'issue_date' => 'Ngày ban hành',
            'task_assignment_type_id' => 'Task assignment type',
            'status' => 'Trạng thái',
            'attachments' => 'Tệp đính kèm',
            'attachments.*' => 'Tệp đính kèm',
            'remove_attachment_ids' => 'Danh sách tệp xóa',
            'remove_attachment_ids.*' => 'ID tệp xóa',
        ];
    }
}
