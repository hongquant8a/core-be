<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserPreference;
use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskPriorityEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskUserAssignmentStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployee;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu mẫu cho phân hệ Quản lý công việc.
 *
 * Chạy SAU PermissionSeeder (cần sẵn permission + 2 vai trò nghiệp vụ).
 * Trình tự: tài khoản → phòng ban → nhân viên → danh mục → văn bản giao việc
 * → công việc → báo cáo.
 *
 * Ba vai trò nghiệp vụ:
 *  - Nhân viên: nhận việc, cập nhật tiến độ, làm báo cáo.
 *  - Quản lý công việc: tạo văn bản, giao việc, theo dõi và xác nhận hoàn thành.
 *  - Trưởng phòng: theo dõi công việc cấp phòng, xử lý đơn thư của phòng.
 */
class TaskAssignmentDemoSeeder extends Seeder
{
    protected const ORG_ID = 1;

    /** Mật khẩu chung cho tài khoản nghiệp vụ mẫu. */
    protected const DEMO_PASSWORD = '123123';

    /** Mốc thời gian cố định để dữ liệu mẫu ổn định giữa các lần seed. */
    protected const BASE_DATE = '2026-08-01 08:00:00';

    protected const DEPARTMENTS = [
        ['name' => 'Phòng Hành chính - Tổng hợp', 'description' => 'Đầu mối tổng hợp, theo dõi tiến độ chung.'],
        ['name' => 'Phòng Kế hoạch - Tài chính', 'description' => 'Lập kế hoạch, dự toán và quyết toán.'],
        ['name' => 'Phòng Kỹ thuật - Công nghệ', 'description' => 'Vận hành hệ thống và hạ tầng kỹ thuật.'],
    ];

    /**
     * user_name => [tên hiển thị, chỉ số phòng ban trong DEPARTMENTS]
     *
     * Tên đặt theo số thứ tự thay vì tên người thật để khi kiểm thử nhìn là biết
     * ngay ai là ai. Mỗi phòng ban có ít nhất 2 nhân viên — dưới 2 thì không thử
     * được các luồng cần nhiều người trong cùng phòng (điều chuyển nội bộ, phạm
     * vi xem đơn thư theo phòng ban).
     *
     * Phân bổ: phòng 0 có 4, phòng 1 có 3, phòng 2 có 3.
     * Năm người đầu giữ nguyên phòng ban cũ vì seedDocumentsAndItems() giao việc
     * theo tên họ — đổi phòng của họ là công việc mẫu dồn hết về một phòng.
     */
    protected const STAFF = [
        'nhanvien1' => ['Nhân viên 1', 0],
        'nhanvien2' => ['Nhân viên 2', 0],
        'nhanvien3' => ['Nhân viên 3', 1],
        'nhanvien4' => ['Nhân viên 4', 1],
        'nhanvien5' => ['Nhân viên 5', 2],
        'nhanvien6' => ['Nhân viên 6', 2],
        'nhanvien7' => ['Nhân viên 7', 0],
        'nhanvien8' => ['Nhân viên 8', 0],
        'nhanvien9' => ['Nhân viên 9', 1],
        'nhanvien10' => ['Nhân viên 10', 2],
    ];

    /** @var array<string, User> */
    protected array $users = [];

    /** @var array<int, TaskAssignmentDepartment> */
    protected array $departments = [];

    /** @var array<string, TaskAssignmentEmployee> */
    protected array $employees = [];

    public function run(): void
    {
        setPermissionsTeamId(self::ORG_ID);

        $this->seedAccounts();
        $this->seedDepartments();
        $this->seedEmployees();
        $this->seedCatalogs();
        $this->seedDocumentsAndItems();
        $this->seedPetitions();

        $this->command?->info('   → '.count($this->users).' tài khoản, '
            .count($this->departments).' phòng ban, '
            .count($this->employees).' nhân viên, '
            .TaskAssignmentDocument::count().' văn bản, '
            .TaskAssignmentItem::withoutGlobalScopes()->count().' công việc, '
            .TaskAssignmentItemReport::count().' báo cáo, '
            .TaskAssignmentPetition::count().' đơn thư.');
    }

    // ── 1. Tài khoản ────────────────────────────────────────────

    protected function seedAccounts(): void
    {
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        $manager = Role::where('name', 'Quản lý công việc')->where('guard_name', 'web')->first();
        $staff = Role::where('name', 'Nhân viên')->where('guard_name', 'web')->first();
        $departmentHead = Role::where('name', 'Trưởng phòng')->where('guard_name', 'web')->first();

        // Tài khoản quản trị mặc định.
        $this->users['admin'] = $this->upsertUser('admin', 'Quản trị hệ thống', 'admin@example.com', 'quandcore**11', $superAdmin);

        // Quản lý công việc.
        $this->users['quanly1'] = $this->upsertUser('quanly1', 'Quản lý 1', 'quanly1@example.com', self::DEMO_PASSWORD, $manager);

        // Nhân viên thực hiện.
        foreach (self::STAFF as $userName => [$fullName]) {
            $this->users[$userName] = $this->upsertUser($userName, $fullName, "{$userName}@example.com", self::DEMO_PASSWORD, $staff);
        }

        // Trưởng phòng: mỗi phòng ban một người.
        if (! $departmentHead) {
            $this->command?->warn('   → Không tìm thấy vai trò "Trưởng phòng" — tài khoản truongphong* sẽ không được gán vai trò.');
        }

        foreach ($this->departmentHeads() as $userName => [$fullName]) {
            $this->users[$userName] = $this->upsertUser($userName, $fullName, "{$userName}@example.com", self::DEMO_PASSWORD, $departmentHead);
        }
    }

    /**
     * Trưởng phòng sinh theo số phòng ban: truongphong1..n khớp thứ tự DEPARTMENTS.
     *
     * Vai trò `Trưởng phòng` được tạo sẵn trong database (không nằm trong
     * PermissionSeeder) nên chỉ tra cứu, không tự tạo.
     *
     * @return array<string, array{0: string, 1: int}> user_name => [tên hiển thị, chỉ số phòng ban]
     */
    protected function departmentHeads(): array
    {
        $heads = [];

        foreach (array_keys(self::DEPARTMENTS) as $i) {
            $heads['truongphong'.($i + 1)] = ['Trưởng phòng '.($i + 1), $i];
        }

        return $heads;
    }

    protected function upsertUser(string $userName, string $name, string $email, string $password, ?Role $role): User
    {
        $user = User::where('user_name', $userName)->orWhere('email', $email)->first();

        if ($user) {
            // Luôn đặt lại mật khẩu để tài khoản mẫu dùng được ngay sau mỗi lần seed.
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'user_name' => $userName,
                'password' => $password,
                'status' => StatusEnum::Active->value,
                'email_verified_at' => now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'user_name' => $userName,
                'password' => $password,
                'status' => StatusEnum::Active->value,
                'email_verified_at' => now(),
            ]);
        }

        if ($role) {
            $user->syncRoles([$role]);
        }

        // Tổ chức hiện tại lấy từ preference — không đặt thì sau khi đăng nhập
        // user rơi vào màn chọn tổ chức và chưa có vai trò nào.
        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['current_organization_id' => self::ORG_ID]
        );

        return $user;
    }

    // ── 2. Phòng ban ────────────────────────────────────────────

    protected function seedDepartments(): void
    {
        foreach (self::DEPARTMENTS as $i => $data) {
            $this->departments[$i] = TaskAssignmentDepartment::withoutGlobalScopes()->updateOrCreate(
                ['name' => $data['name'], 'organization_id' => self::ORG_ID],
                [
                    'description' => $data['description'],
                    'status' => StatusEnum::Active->value,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    // ── 3. Nhân viên phân hệ + gán phòng ban ────────────────────

    protected function seedEmployees(): void
    {
        // Quản lý công việc thuộc phòng tổng hợp và là người đại diện phòng đó.
        $this->employees['quanly1'] = $this->upsertEmployee($this->users['quanly1'], 0, true);

        foreach (self::STAFF as $userName => [, $deptIndex]) {
            $this->employees[$userName] = $this->upsertEmployee($this->users[$userName], $deptIndex, false);
        }

        // Trưởng phòng là nhân viên của phòng mình. Không đặt cờ đại diện: đại diện
        // hiện có (quanly1, nhanvien3, nhanvien5) giữ nguyên, mỗi phòng một người.
        foreach ($this->departmentHeads() as $userName => [, $deptIndex]) {
            $this->employees[$userName] = $this->upsertEmployee($this->users[$userName], $deptIndex, false);
        }

        // Mỗi phòng còn lại cần một người đại diện thì mới giao việc cho cả phòng được.
        foreach ([1 => 'nhanvien3', 2 => 'nhanvien5'] as $deptIndex => $userName) {
            TaskAssignmentEmployeeDepartment::withoutGlobalScopes()
                ->where('task_assignment_employee_id', $this->employees[$userName]->id)
                ->where('task_assignment_department_id', $this->departments[$deptIndex]->id)
                ->update(['is_representative' => true]);

            // Người đại diện là trưởng phòng trên thực tế, nên cấp thêm quyền xem
            // dữ liệu cấp phòng. Không có quyền này thì họ chỉ thấy công việc của
            // chính mình — đúng như mọi nhân viên khác.
            //
            // Cấp thẳng cho người dùng chứ không gắn vào vai trò `Nhân viên`: gắn
            // vào vai trò là MỌI nhân viên đều thấy cả phòng, khác hẳn ý đồ.
            // quanly1 (đại diện phòng 0) không cần vì đã có `task-overview.viewAll`.
            $this->users[$userName]->givePermissionTo('task-overview.viewDepartment');
        }
    }

    protected function upsertEmployee(User $user, int $deptIndex, bool $isRepresentative): TaskAssignmentEmployee
    {
        $employee = TaskAssignmentEmployee::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $user->id, 'organization_id' => self::ORG_ID],
            ['status' => StatusEnum::Active->value]
        );

        TaskAssignmentEmployeeDepartment::withoutGlobalScopes()->updateOrCreate(
            [
                'task_assignment_employee_id' => $employee->id,
                'task_assignment_department_id' => $this->departments[$deptIndex]->id,
            ],
            ['organization_id' => self::ORG_ID, 'is_representative' => $isRepresentative]
        );

        return $employee;
    }

    // ── 4. Danh mục ─────────────────────────────────────────────

    protected function seedCatalogs(): void
    {
        foreach (['Công văn', 'Kế hoạch', 'Thông báo'] as $i => $name) {
            TaskAssignmentType::withoutGlobalScopes()->updateOrCreate(
                ['name' => $name, 'organization_id' => self::ORG_ID],
                ['status' => StatusEnum::Active->value]
            );
        }

        foreach (['Công việc thường xuyên', 'Công việc đột xuất', 'Công việc trọng tâm'] as $name) {
            TaskAssignmentItemType::withoutGlobalScopes()->updateOrCreate(
                ['name' => $name, 'organization_id' => self::ORG_ID],
                ['status' => StatusEnum::Active->value]
            );
        }
    }

    // ── 5. Văn bản giao việc + công việc + báo cáo ──────────────

    protected function seedDocumentsAndItems(): void
    {
        $base = Carbon::parse(self::BASE_DATE);
        $docType = TaskAssignmentType::withoutGlobalScopes()->where('organization_id', self::ORG_ID)->orderBy('id')->first();
        $itemTypes = TaskAssignmentItemType::withoutGlobalScopes()->where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $assigner = $this->users['quanly1'];

        $plan = [
            [
                'doc' => 'Công văn 101/CV-VP về triển khai nhiệm vụ quý III',
                'summary' => 'Giao các phòng triển khai nhiệm vụ trọng tâm quý III/2026.',
                'items' => [
                    // [tên công việc, người thực hiện, trạng thái, % hoàn thành, số ngày hạn]
                    ['Rà soát và cập nhật quy trình nội bộ', 'nhanvien1', TaskProgressStatusEnum::Done, 100, 10],
                    ['Tổng hợp báo cáo nhân sự quý III', 'nhanvien2', TaskProgressStatusEnum::PendingApproval, 100, 14],
                    ['Lập dự toán kinh phí quý IV', 'nhanvien3', TaskProgressStatusEnum::InProgress, 60, 20],
                ],
            ],
            [
                'doc' => 'Kế hoạch 205/KH-VP về nâng cấp hạ tầng công nghệ',
                'summary' => 'Nâng cấp hạ tầng máy chủ và bảo đảm an toàn thông tin.',
                'items' => [
                    ['Khảo sát hiện trạng máy chủ', 'nhanvien5', TaskProgressStatusEnum::InProgress, 40, 15],
                    ['Đối chiếu quyết toán kinh phí năm 2026', 'nhanvien4', TaskProgressStatusEnum::Todo, 0, 25],
                    ['Xây dựng phương án sao lưu dữ liệu', 'nhanvien5', TaskProgressStatusEnum::Todo, 0, 30],
                ],
            ],
        ];

        foreach ($plan as $d => $entry) {
            $issueDate = $base->copy()->addDays($d * 7);

            $document = TaskAssignmentDocument::withoutGlobalScopes()->updateOrCreate(
                ['name' => $entry['doc'], 'organization_id' => self::ORG_ID],
                [
                    'summary' => $entry['summary'],
                    'issue_date' => $issueDate->toDateString(),
                    'task_assignment_type_id' => $docType?->id,
                    'status' => TaskAssignmentDocumentStatusEnum::Issued->value,
                    'issued_at' => $issueDate,
                    'created_by' => $assigner->id,
                    'updated_by' => $assigner->id,
                ]
            );

            foreach ($entry['items'] as $i => [$itemName, $staffKey, $status, $percent, $deadlineDays]) {
                $employee = $this->employees[$staffKey];
                $performer = $this->users[$staffKey];
                $membership = TaskAssignmentEmployeeDepartment::withoutGlobalScopes()
                    ->where('task_assignment_employee_id', $employee->id)
                    ->first();

                $startAt = $issueDate->copy()->addDay();
                $endAt = $issueDate->copy()->addDays($deadlineDays);
                $isDone = $status === TaskProgressStatusEnum::Done;
                $isReported = $percent >= 100;

                $item = TaskAssignmentItem::withoutGlobalScopes()->updateOrCreate(
                    ['name' => $itemName, 'task_assignment_document_id' => $document->id],
                    [
                        'task_assignment_item_type_id' => $itemTypes[$i % count($itemTypes)] ?? null,
                        'deadline_type' => TaskDeadlineTypeEnum::HasDeadline->value,
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                        'processing_status' => $status->value,
                        'completion_percent' => $percent,
                        'priority' => [TaskPriorityEnum::High, TaskPriorityEnum::Medium, TaskPriorityEnum::Low][$i % 3]->value,
                        'assigned_by' => $assigner->id,
                        'reported_by' => $isReported ? $performer->id : null,
                        'reported_at' => $isReported ? $endAt->copy()->subDays(2) : null,
                        'approved_by' => $isDone ? $assigner->id : null,
                        'completed_at' => $isDone ? $endAt->copy()->subDay() : null,
                        'organization_id' => self::ORG_ID,
                        'created_by' => $assigner->id,
                        'updated_by' => $assigner->id,
                    ]
                );

                // Phân công người thực hiện (pivot khoá theo user_id, xem CLAUDE.md).
                DB::table('task_assignment_item_user')->updateOrInsert(
                    ['task_assignment_item_id' => $item->id, 'user_id' => $performer->id],
                    [
                        'department_id' => $membership->task_assignment_department_id,
                        'department_role' => 'main',
                        'assignment_role' => 'main',
                        'assignment_status' => $isReported
                            ? TaskUserAssignmentStatusEnum::Done->value
                            : TaskUserAssignmentStatusEnum::Assigned->value,
                        'assigned_at' => $startAt,
                        'completed_at' => $isDone ? $endAt->copy()->subDay() : null,
                        'created_at' => $startAt,
                        'updated_at' => $startAt,
                    ]
                );

                // Báo cáo mẫu: chỉ những công việc đã báo cáo (100%) mới có.
                if ($isReported) {
                    TaskAssignmentItemReport::withoutGlobalScopes()->updateOrCreate(
                        ['task_assignment_item_id' => $item->id, 'reporter_user_id' => $performer->id],
                        [
                            'assignee_user_id' => $performer->id,
                            'completion_percent' => 100,
                            'completed_at' => $endAt->copy()->subDays(2),
                            'report_document_number' => sprintf('BC-%02d/%02d', $d + 1, $i + 1),
                            'report_document_excerpt' => "Báo cáo kết quả thực hiện: {$itemName}.",
                            'report_document_content' => "Đã hoàn thành nội dung được giao tại văn bản \"{$entry['doc']}\". "
                                .'Kết quả đạt yêu cầu về tiến độ và chất lượng, không phát sinh vướng mắc.',
                            'organization_id' => self::ORG_ID,
                            'created_by' => $performer->id,
                            'updated_by' => $performer->id,
                        ]
                    );
                }
            }
        }
    }

    // ── 6. Đơn thư ──────────────────────────────────────────────

    /**
     * Mỗi phòng ban một đơn thư để kiểm chứng phạm vi xem:
     * nhân viên chỉ thấy đơn của phòng mình, phòng tổng hợp thấy tất cả.
     */
    protected function seedPetitions(): void
    {
        $base = Carbon::parse(self::BASE_DATE);

        $plan = [
            [0, 'Nguyễn Thị Hoa', 'Kiến nghị về thủ tục hành chính tại bộ phận một cửa.', PetitionStatusEnum::New],
            [1, 'Trần Văn Bảy', 'Phản ánh việc chậm thanh toán chế độ hỗ trợ.', PetitionStatusEnum::Processing],
            [2, 'Lê Thị Cúc', 'Đề nghị hỗ trợ khắc phục sự cố đường truyền mạng.', PetitionStatusEnum::Completed],
        ];

        foreach ($plan as $i => [$deptIndex, $sender, $content, $status]) {
            $submittedAt = $base->copy()->addDays($i * 3);
            $isDone = $status === PetitionStatusEnum::Completed;

            TaskAssignmentPetition::withoutGlobalScopes()->updateOrCreate(
                [
                    'department_id' => $this->departments[$deptIndex]->id,
                    'sender_name' => $sender,
                ],
                [
                    'submission_date' => $submittedAt->toDateString(),
                    'deadline_date' => $submittedAt->copy()->addDays(15)->toDateString(),
                    'sender_phone' => '09'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                    'content' => $content,
                    'processing_status' => $status->value,
                    'completed_at' => $isDone ? $submittedAt->copy()->addDays(10) : null,
                    'document_number' => sprintf('DT-%02d/2026', $i + 1),
                    'document_excerpt' => $content,
                    'response_content' => $isDone ? 'Đã xử lý và phản hồi công dân bằng văn bản.' : null,
                    'organization_id' => self::ORG_ID,
                ]
            );
        }
    }
}
