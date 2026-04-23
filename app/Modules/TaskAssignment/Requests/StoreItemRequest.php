<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentRoleEnum;
use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskPriorityEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskUserAssignmentRoleEnum;

class StoreItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'task_assignment_document_id' => 'required|integer|exists:task_assignment_documents,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'task_assignment_item_type_id' => 'nullable|integer|exists:task_assignment_item_types,id',
            'deadline_type' => ['required', TaskDeadlineTypeEnum::rule()],
            'start_at' => 'nullable|date',
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at', 'required_if:deadline_type,has_deadline'],
            'processing_status' => ['nullable', TaskProgressStatusEnum::selectableRule()],
            'completion_percent' => 'nullable|integer|min:0|max:100',
            'priority' => ['nullable', TaskPriorityEnum::rule()],
            'assigned_by' => 'nullable|integer|exists:users,id',
            'users' => 'nullable|array',
            'users.*.user_id' => 'required|integer|exists:users,id',
            'users.*.department_id' => 'required|integer|exists:task_assignment_departments,id',
            'users.*.department_role' => ['required', TaskAssignmentRoleEnum::rule()],
            'users.*.assignment_role' => ['required', TaskUserAssignmentRoleEnum::rule()],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif|max:20480',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'task_assignment_document_id' => [
                'description' => 'ID văn bản giao việc.',
                'example' => 1,
            ],
            'name' => [
                'description' => 'Tên công việc.',
                'example' => 'Lập báo cáo tài chính tháng 4',
            ],
            'description' => [
                'description' => 'Mô tả chi tiết công việc.',
                'example' => 'Lập báo cáo tài chính và nộp cho Ban giám đốc.',
            ],
            'task_assignment_item_type_id' => [
                'description' => 'ID loại hạng mục công việc.',
                'example' => 1,
            ],
            'deadline_type' => [
                'description' => 'Loại thời hạn công việc.',
                'example' => TaskDeadlineTypeEnum::HasDeadline->value,
            ],
            'start_at' => [
                'description' => 'Ngày bắt đầu công việc.',
                'example' => '2026-04-01',
            ],
            'end_at' => [
                'description' => 'Ngày kết thúc công việc. Bắt buộc khi deadline_type là has_deadline.',
                'example' => '2026-04-30',
            ],
            'processing_status' => [
                'description' => 'Trạng thái xử lý công việc.',
                'example' => TaskProgressStatusEnum::Todo->value,
            ],
            'completion_percent' => [
                'description' => 'Phần trăm hoàn thành công việc (0-100).',
                'example' => 0,
            ],
            'priority' => [
                'description' => 'Mức độ ưu tiên công việc.',
                'example' => TaskPriorityEnum::Medium->value,
            ],
            'assigned_by' => [
                'description' => 'ID người giao việc (quản trị).',
                'example' => 1,
            ],
            'users' => [
                'description' => 'Danh sách người thực hiện (Phòng ban → User → Vai trò).',
                'example' => [['user_id' => 1, 'department_id' => 1, 'department_role' => TaskAssignmentRoleEnum::Main->value, 'assignment_role' => TaskUserAssignmentRoleEnum::Main->value]],
            ],
            'users.*.user_id' => [
                'description' => 'ID người dùng.',
                'example' => 1,
            ],
            'users.*.department_id' => [
                'description' => 'ID phòng ban của người dùng.',
                'example' => 1,
            ],
            'users.*.department_role' => [
                'description' => 'Vai trò của phòng ban trong công việc (chủ trì/phối hợp).',
                'example' => TaskAssignmentRoleEnum::Main->value,
            ],
            'users.*.assignment_role' => [
                'description' => 'Vai trò của người dùng trong công việc (chủ trì/hỗ trợ).',
                'example' => TaskUserAssignmentRoleEnum::Main->value,
            ],
        ];
    }
}
