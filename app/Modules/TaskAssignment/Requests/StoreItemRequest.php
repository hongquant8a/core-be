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
            'assigned_by' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (! $value) {
                    return;
                }
                $orgId = getPermissionsTeamId();
                $exists = \Illuminate\Support\Facades\DB::table('task_assignment_users')
                    ->where('user_id', $value)
                    ->where('organization_id', $orgId)
                    ->where('status', 'active')
                    ->exists();
                if (! $exists) {
                    $fail("Người giao việc (ID {$value}) không thuộc module giao việc của tổ chức này.");
                }
            }],
            'users' => 'nullable|array',
            'users.*.user_id' => 'required|integer',
            'users.*.department_id' => 'required|integer|exists:task_assignment_departments,id',
            'users.*.department_role' => ['required', TaskAssignmentRoleEnum::rule()],
            'users.*.assignment_role' => ['required', TaskUserAssignmentRoleEnum::rule()],
            'users.*' => [function ($attribute, $value, $fail) {
                if (! is_array($value) || empty($value['user_id']) || empty($value['department_id'])) {
                    return;
                }
                $orgId = getPermissionsTeamId();
                $exists = \Illuminate\Support\Facades\DB::table('task_assignment_users')
                    ->where('user_id', $value['user_id'])
                    ->where('task_assignment_department_id', $value['department_id'])
                    ->where('organization_id', $orgId)
                    ->where('status', 'active')
                    ->exists();
                if (! $exists) {
                    $fail("User ID {$value['user_id']} không thuộc phòng ban ID {$value['department_id']} trong tổ chức này.");
                }
            }],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
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
                'example' => 'Soạn thảo báo cáo tình hình nhân sự tháng 4',
            ],
            'description' => [
                'description' => 'Mô tả chi tiết công việc.',
                'example' => 'Yêu cầu tổng hợp số liệu từ các phòng ban, hạn chót báo cáo sơ bộ 25/04.',
            ],
            'task_assignment_item_type_id' => [
                'description' => 'ID loại công việc (nhóm công việc).',
                'example' => 1,
            ],
            'deadline_type' => [
                'description' => 'Loại thời hạn (có thời hạn, không thời hạn, theo văn bản chỉ đạo).',
                'example' => 'has_deadline',
            ],
            'start_at' => [
                'description' => 'Thời gian bắt đầu (Y-m-d H:i:s).',
                'example' => '2026-04-10 08:00:00',
            ],
            'end_at' => [
                'description' => 'Thời gian kết thúc / Hạn chót (Y-m-d H:i:s).',
                'example' => '2026-04-30 17:00:00',
            ],
            'processing_status' => [
                'description' => 'Trạng thái xử lý (todo, in_progress, paused, cancelled, done).',
                'example' => 'todo',
            ],
            'completion_percent' => [
                'description' => 'Phần trăm hoàn thành (0-100).',
                'example' => 0,
            ],
            'priority' => [
                'description' => 'Mức độ ưu tiên (low, medium, high, urgent).',
                'example' => 'medium',
            ],
            'assigned_by' => [
                'description' => 'ID người giao việc (quản trị).',
                'example' => 1,
            ],
            'users' => [
                'description' => 'Danh sách người thực hiện công việc (cập nhật lại toàn bộ danh sách).',
            ],
            'users.*.user_id' => [
                'description' => 'ID người dùng.',
                'example' => 1,
            ],
            'users.*.department_id' => [
                'description' => 'ID phòng ban của người dùng (tại thời điểm phân công).',
                'example' => 2,
            ],
            'users.*.department_role' => [
                'description' => 'Vai trò của phòng ban (chủ trì, phối hợp).',
                'example' => 'main',
            ],
            'users.*.assignment_role' => [
                'description' => 'Vai trò của người dùng (xử lý chính, người phối hợp, theo dõi).',
                'example' => 'main',
            ],
            'attachments' => [
                'description' => 'Danh sách tệp đính kèm. Có thể truyền file mới (multipart/form-data) hoặc truyền chuỗi JSON/object của file cũ để giữ lại. Tối đa 10 tệp, mỗi tệp 20MB.',
                'example' => [],
            ],
        ];
    }
}
