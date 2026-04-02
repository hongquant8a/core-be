<?php

namespace App\Modules\TaskAssignment\Requests;

class StoreReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'task_assignment_item_id' => 'required|integer|exists:task_assignment_items,id',
            'completed_at' => 'nullable|date',
            'report_document_number' => 'nullable|string|max:255',
            'report_document_excerpt' => 'nullable|string|max:65535',
            'report_document_content' => 'nullable|string',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:20480',
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
            'files' => [
                'description' => 'Danh sách tệp đính kèm (tối đa 10 tệp, mỗi tệp tối đa 20MB).',
                'example' => [],
            ],
            'files.*' => [
                'description' => 'Tệp đính kèm (định dạng pdf, doc, docx, xls, xlsx, ppt, pptx).',
            ],
        ];
    }
}
