<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentRoleEnum;
use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskPriorityEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskUserAssignmentRoleEnum;

class StoreItemRequest extends BaseRequest
{
    public function prepareForValidation(): void
    {
        $this->resolveDepartmentsToUsers();
    }

    public function rules(): array
    {
        return [
            'task_assignment_document_id' => 'required|integer|exists:task_assignment_documents,id',
            // Cột `name` đã đổi sang TEXT (~65535 byte). `max` ở đây đếm KÝ TỰ,
            // mà tiếng Việt có dấu tốn tới 3 byte/ký tự → chốt 20000 để 3 byte
            // vẫn nằm dưới trần TEXT. Mục đích chỉ là trả 422 sạch thay vì để
            // MySQL strict mode ném SQLSTATE 22001 thành lỗi 500.
            'name' => 'required|string|max:20000',
            'description' => 'nullable|string|max:65535',
            'task_assignment_item_type_id' => 'nullable|integer|exists:task_assignment_item_types,id',
            'deadline_type' => ['required', TaskDeadlineTypeEnum::rule()],
            'start_at' => 'nullable|date',
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at', 'required_if:deadline_type,has_deadline'],
            'processing_status' => ['nullable', TaskProgressStatusEnum::selectableRule()],
            'completion_percent' => 'nullable|integer|min:0|max:100',
            'priority' => ['nullable', TaskPriorityEnum::rule()],
            'assigned_by' => ['required', 'integer', function ($attribute, $value, $fail) {
                if (! $value) {
                    return;
                }
                $orgId = getPermissionsTeamId();
                $exists = \Illuminate\Support\Facades\DB::table('task_assignment_employees')
                    ->where('user_id', $value)
                    ->where('organization_id', $orgId)
                    ->where('status', 'active')
                    ->exists();
                if (! $exists) {
                    $fail("Người giao việc (ID {$value}) không phải nhân viên module giao việc của tổ chức này.");
                }
            }],
            'users' => 'required_without:departments|array|min:1',
            'departments' => 'required_without:users|array|min:1',
            'departments.*.department_id' => 'required|integer|exists:task_assignment_departments,id',
            'departments.*.department_role' => ['required', TaskAssignmentRoleEnum::rule()],
            'departments.*' => [function ($attribute, $value, $fail) {
                if (! is_array($value) || empty($value['department_id'])) {
                    return;
                }
                $orgId = getPermissionsTeamId();
                $hasRep = \Illuminate\Support\Facades\DB::table('task_assignment_users')
                    ->where('task_assignment_department_id', $value['department_id'])
                    ->where('organization_id', $orgId)
                    ->where('is_representative', true)
                    ->where('status', 'active')
                    ->exists();
                if (! $hasRep) {
                    $fail("Phòng ban ID {$value['department_id']} chưa có người đại diện.");
                }
            }],
            'users.*.user_id' => 'required|integer',
            'users.*.department_id' => 'required|integer|exists:task_assignment_departments,id',
            'users.*.department_role' => ['required', TaskAssignmentRoleEnum::rule()],
            'users.*.assignment_role' => ['required', TaskUserAssignmentRoleEnum::rule()],
            'users.*' => [function ($attribute, $value, $fail) {
                if (! is_array($value) || empty($value['user_id']) || empty($value['department_id'])) {
                    return;
                }
                $orgId = getPermissionsTeamId();

                // Gate 1: phải là nhân viên module Task đang active (đăng ký qua task-assignment-employees).
                $isEmployee = \Illuminate\Support\Facades\DB::table('task_assignment_employees')
                    ->where('user_id', $value['user_id'])
                    ->where('organization_id', $orgId)
                    ->where('status', 'active')
                    ->exists();
                if (! $isEmployee) {
                    $fail("User ID {$value['user_id']} không phải nhân viên module Task hoặc đã bị vô hiệu hóa. Vui lòng đăng ký nhân viên trước.");
                    return;
                }

                // Gate 2: phải thuộc đúng dept đang gán.
                $inDept = \Illuminate\Support\Facades\DB::table('task_assignment_users')
                    ->where('user_id', $value['user_id'])
                    ->where('task_assignment_department_id', $value['department_id'])
                    ->where('organization_id', $orgId)
                    ->where('status', 'active')
                    ->exists();
                if (! $inDept) {
                    $fail("User ID {$value['user_id']} không thuộc phòng ban ID {$value['department_id']} trong tổ chức này.");
                }
            }],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_by.required' => 'Phải chọn người giao việc.',
            'users.required' => 'Phải phân công ít nhất 1 người thực hiện.',
            'users.required_without' => 'Phải cung cấp users hoặc departments.',
            'users.array' => 'Danh sách người thực hiện không hợp lệ.',
            'users.min' => 'Phải phân công ít nhất 1 người thực hiện.',
            'departments.required_without' => 'Phải cung cấp users hoặc departments.',
            'departments.array' => 'Danh sách phòng ban không hợp lệ.',
            'departments.min' => 'Phải chọn ít nhất 1 phòng ban.',
            'departments.*.department_id.required' => 'Phải chọn phòng ban.',
            'departments.*.department_id.exists' => 'Phòng ban không tồn tại.',
            'departments.*.department_role.required' => 'Phải chọn vai trò phòng ban.',
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
                'description' => 'Danh sách người thực hiện công việc. Có thể thay bằng departments để tự động lấy đại diện phòng ban.',
            ],
            'departments' => [
                'description' => 'Danh sách phòng ban (thay cho users). Hệ thống tự lấy người đại diện của từng phòng ban. Phòng ban phải đã có người đại diện (is_representative=true).',
            ],
            'departments.*.department_id' => [
                'description' => 'ID phòng ban.',
                'example' => 2,
            ],
            'departments.*.department_role' => [
                'description' => 'Vai trò phòng ban (main=chủ trì, cooperate=phối hợp).',
                'example' => 'main',
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

    public function attributes(): array
    {
        return [
            'task_assignment_document_id' => 'Văn bản giao việc',
            'name' => 'Tên',
            'description' => 'Mô tả',
            'task_assignment_item_type_id' => 'Loại công việc',
            'deadline_type' => 'Loại thời hạn',
            'start_at' => 'Thời gian bắt đầu',
            'end_at' => 'Thời gian kết thúc',
            'processing_status' => 'Trạng thái xử lý',
            'completion_percent' => 'Phần trăm hoàn thành',
            'priority' => 'Mức ưu tiên',
            'assigned_by' => 'Người giao việc',
            'users' => 'Danh sách người thực hiện',
            'users.*' => 'Người thực hiện',
            'departments' => 'Danh sách phòng ban',
            'departments.*' => 'Phòng ban',
            'departments.*.department_id' => 'Phòng ban',
            'departments.*.department_role' => 'Vai trò phòng ban',
            'attachments' => 'Tệp đính kèm',
            'attachments.*' => 'Tệp đính kèm',
        ];
    }
}
