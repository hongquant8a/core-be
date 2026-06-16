<?php

namespace App\Modules\TaskAssignment\Requests;

class StoreReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'task_assignment_item_id' => 'required|integer|exists:task_assignment_items,id',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
            'completion_percent' => 'nullable|integer|min:0|max:100',
            'completed_at' => 'nullable|date',
            'report_document_number' => 'nullable|string|max:255',
            'report_document_excerpt' => 'nullable|string|max:65535',
            'report_document_content' => 'nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'assignee_user_id' => [
                'description' => 'ID người thực hiện công việc (từ danh sách người dùng thuộc phòng ban được giao của task).',
                'example' => 5,
            ],
            'completion_percent' => [
                'description' => 'Tiến độ hoàn thành (0-100). Nếu đạt 100%, công việc sẽ chuyển sang trạng thái chờ duyệt.',
                'example' => 100,
            ],
            'completed_at' => [
                'description' => 'Ngày hoàn thành công việc.',
                'example' => '2026-04-30',
            ],
            'report_document_number' => [
                'description' => 'Số hiệu văn bản báo cáo.',
                'example' => 'BC-01/2026',
            ],
            'report_document_excerpt' => [
                'description' => 'Trích yếu nội dung báo cáo.',
                'example' => 'Báo cáo kết quả thực hiện công việc tháng 4/2026.',
            ],
            'report_document_content' => [
                'description' => 'Nội dung chi tiết báo cáo.',
                'example' => 'Nội dung báo cáo đầy đủ...',
            ],
            'attachments' => [
                'description' => 'Danh sách tệp đính kèm. Có thể truyền file mới (multipart/form-data) hoặc truyền chuỗi JSON/object của file cũ để giữ lại. Tối đa 10 tệp, mỗi tệp tối đa 20MB.',
                'example' => [],
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'task_assignment_item_id' => 'Công việc',
            'assignee_user_id' => 'Người thực hiện',
            'completion_percent' => 'Tiến độ hoàn thành',
            'completed_at' => 'Thời gian hoàn thành',
            'report_document_number' => 'Số văn bản báo cáo',
            'report_document_excerpt' => 'Report document excerpt',
            'report_document_content' => 'Report document content',
            'attachments' => 'Tệp đính kèm',
            'attachments.*' => 'Tệp đính kèm',
        ];
    }
}
