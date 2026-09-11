<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class StoreDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Cột `name` đã đổi sang TEXT (~65535 byte). `max` ở đây đếm KÝ TỰ,
            // mà tiếng Việt có dấu tốn tới 3 byte/ký tự → chốt 20000 để 3 byte
            // vẫn nằm dưới trần TEXT. Mục đích chỉ là trả 422 sạch thay vì để
            // MySQL strict mode ném SQLSTATE 22001 thành lỗi 500.
            'name' => 'required|string|max:20000',
            'summary' => 'nullable|string|max:65535',
            // Nguyên văn đã dán vào ô phân tích AI. Trần 50.000 khớp
            // AnalyzeDocumentRequest: dài hơn thì AI đã từ chối phân tích rồi,
            // không có đường nào tạo ra chuỗi hợp lệ dài hơn thế.
            'ai_source_content' => 'nullable|string|max:50000',
            'issue_date' => 'nullable|date',
            'task_assignment_type_id' => 'nullable|integer|exists:task_assignment_types,id',
            'status' => ['required', TaskAssignmentDocumentStatusEnum::rule()],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên văn bản.',
            'name.max' => 'Tên văn bản không được vượt quá 255 ký tự.',
            'ai_source_content.max' => 'Nội dung văn bản gốc không được vượt quá :max ký tự.',
            'issue_date.date' => 'Ngày ban hành không đúng định dạng.',
            'task_assignment_type_id.required' => 'Vui lòng chọn loại văn bản.',
            'task_assignment_type_id.exists' => 'Loại văn bản không tồn tại.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'attachments.max' => 'Tối đa 10 tệp đính kèm.',
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
            'ai_source_content' => 'Nội dung văn bản gốc',
            'issue_date' => 'Ngày ban hành',
            'task_assignment_type_id' => 'Task assignment type',
            'status' => 'Trạng thái',
            'attachments' => 'Tệp đính kèm',
            'attachments.*' => 'Tệp đính kèm',
        ];
    }
}
