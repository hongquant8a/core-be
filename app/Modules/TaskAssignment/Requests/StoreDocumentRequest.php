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
            'reminders' => 'nullable|array',
            'reminders.*.reminder_type' => 'required|string|in:instant,scheduled',
            'reminders.*.channels' => 'required|array',
            'reminders.*.channels.*' => 'string',
            'reminders.*.moment' => 'nullable|string|in:before,on,after',
            'reminders.*.offset_minutes' => 'nullable|integer|min:0',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên văn bản.',
            'name.max' => 'Tên văn bản không được vượt quá 255 ký tự.',
            'issue_date.date' => 'Ngày ban hành không đúng định dạng.',
            'task_assignment_type_id.required' => 'Vui lòng chọn loại văn bản.',
            'task_assignment_type_id.exists' => 'Loại văn bản không tồn tại.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'reminders.*.reminder_type.required' => 'Vui lòng chọn loại reminder.',
            'reminders.*.reminder_type.in' => 'Loại reminder phải là instant hoặc scheduled.',
            'reminders.*.channels.required' => 'Vui lòng chọn kênh thông báo.',
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
            'reminders' => [
                'description' => 'Danh sách reminders. Mỗi reminder có reminder_type (instant|scheduled), channels. Với scheduled thêm moment + offset_minutes.',
                'example' => '[{"reminder_type":"instant","channels":["mail"]}]',
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
            'reminders' => 'Reminders',
            'reminders.*.reminder_type' => 'Loại reminder',
            'reminders.*.channels' => 'Kênh thông báo',
            'reminders.*.moment' => 'Thời điểm',
            'reminders.*.offset_minutes' => 'Số phút',
        ];
    }
}
