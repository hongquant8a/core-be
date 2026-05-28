<?php

namespace App\Modules\TaskAssignment\Requests;

class UpdateReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'completed_at' => 'sometimes|nullable|date',
            'report_document_number' => 'sometimes|nullable|string|max:255',
            'report_document_excerpt' => 'sometimes|nullable|string|max:65535',
            'report_document_content' => 'sometimes|nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
        ];
    }

    public function bodyParameters(): array
    {
        return [
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
            'remove_attachment_ids' => [
                'description' => 'Danh sách ID tệp đính kèm cần xóa.',
                'example' => [1, 2],
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'completed_at' => 'Thời gian hoàn thành',
            'report_document_number' => 'Số văn bản báo cáo',
            'report_document_excerpt' => 'Report document excerpt',
            'report_document_content' => 'Report document content',
            'attachments' => 'Tệp đính kèm',
            'attachments.*' => 'Tệp đính kèm',
            'remove_attachment_ids' => 'Danh sách tệp xóa',
            'remove_attachment_ids.*' => 'ID tệp xóa',
        ];
    }
}
