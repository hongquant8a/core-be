<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TaskAssignmentSampleSeeder extends Seeder
{
    public function run(): void
    {
        // Thiết lập team_id cho Permission (Spatie)
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(1);
        }

        // 1. Tạo/Cập nhật Users
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Quản lý Mẫu',
                'user_name' => 'admin_sample',
                'password' => Hash::make('password'),
            ]
        );

        $staff = User::updateOrCreate(
            ['email' => 'nhanvien@example.com'],
            [
                'name' => 'Nhân viên Mẫu',
                'user_name' => 'nhanvien_sample',
                'password' => Hash::make('password'),
            ]
        );

        // Gán Role (Giả định Role Admin và Trưởng phòng đã tồn tại từ PermissionSeeder)
        $admin->assignRole('Trưởng phòng');
        $staff->assignRole('Nhân viên');

        // 2. Đảm bảo có Phòng ban
        $dept = TaskAssignmentDepartment::firstOrCreate(
            ['name' => 'Phòng Thông tin - Tổng hợp'],
            ['status' => 'active', 'organization_id' => 1]
        );

        // 3. Gán User vào module TaskAssignment
        TaskAssignmentUser::updateOrCreate(
            ['user_id' => $admin->id, 'organization_id' => 1],
            ['task_assignment_department_id' => $dept->id, 'status' => 'active']
        );

        TaskAssignmentUser::updateOrCreate(
            ['user_id' => $staff->id, 'organization_id' => 1],
            ['task_assignment_department_id' => $dept->id, 'status' => 'active']
        );

        // 4. Tạo dữ liệu công việc mẫu (Dùng updateOrCreate để không bị trùng khi chạy lại)
        $type = TaskAssignmentType::firstOrCreate(['name' => 'Thường trực Thành ủy giao'], ['organization_id' => 1]);
        $itemType = TaskAssignmentItemType::firstOrCreate(['name' => 'Soạn thảo văn bản'], ['organization_id' => 1]);

        $doc = TaskAssignmentDocument::updateOrCreate(
            ['name' => 'Văn bản test điều chuyển'],
            [
                'summary' => 'Văn bản dùng để test tính năng transfer',
                'issue_date' => now()->format('Y-m-d'),
                'status' => 'issued',
                'issued_at' => now(),
                'task_assignment_type_id' => $type->id,
                'organization_id' => 1,
                'created_by' => $admin->id,
            ]
        );

        $item = TaskAssignmentItem::updateOrCreate(
            ['name' => 'Việc cần điều chuyển', 'task_assignment_document_id' => $doc->id],
            [
                'description' => 'Công việc này đang giao cho nhân viên mẫu, hãy thử điều chuyển sang admin hoặc người khác',
                'task_assignment_item_type_id' => $itemType->id,
                'deadline_type' => 'has_deadline',
                'start_at' => now()->subDays(1),
                'end_at' => now()->addDays(7),
                'processing_status' => 'in_progress',
                'organization_id' => 1,
                'created_by' => $admin->id,
            ]
        );

        // Gán việc cho nhân viên (Nếu chưa gán)
        $exists = DB::table('task_assignment_item_user')
            ->where('task_assignment_item_id', $item->id)
            ->where('user_id', $staff->id)
            ->exists();

        if (!$exists) {
            $item->users()->attach($staff->id, [
                'department_id' => $dept->id,
                'department_role' => 'main',
                'assignment_role' => 'main',
                'assignment_status' => 'assigned',
                'assigned_at' => now()->subDays(1),
                'accepted_at' => now()->subDays(1),
            ]);
        }

        // 5. Seed Timeline: Ghi chú (Notes)
        $noteExists = DB::table('task_assignment_item_notes')
            ->where('task_assignment_item_id', $item->id)
            ->exists();

        if (!$noteExists) {
            DB::table('task_assignment_item_notes')->insert([
                [
                    'task_assignment_item_id' => $item->id,
                    'author_user_id' => $admin->id,
                    'author_role' => 'manager',
                    'content' => 'Chào em, anh giao việc này em nghiên cứu triển khai sớm nhé.',
                    'created_at' => now()->subDays(1)->addHours(1),
                    'organization_id' => 1,
                ],
                [
                    'task_assignment_item_id' => $item->id,
                    'author_user_id' => $staff->id,
                    'author_role' => 'assignee',
                    'content' => 'Vâng anh, em đã nhận được việc và đang xem tài liệu ạ.',
                    'created_at' => now()->subDays(1)->addHours(2),
                    'organization_id' => 1,
                ]
            ]);
        }

        // 6. Seed Timeline: Điều chuyển (Transfers)
        $transferExists = DB::table('task_assignment_item_user_transfers')
            ->where('task_assignment_item_id', $item->id)
            ->exists();

        if (!$transferExists) {
            DB::table('task_assignment_item_user_transfers')->insert([
                'task_assignment_item_id' => $item->id,
                'from_user_id' => $admin->id,
                'to_user_id' => $staff->id,
                'note' => 'Chuyển giao chuyên môn cho nhân viên thực hiện.',
                'transferred_by_user_id' => $admin->id,
                'transferred_at' => now()->subDays(1),
                'organization_id' => 1,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ]);
        }
        
        $this->command->info('Seed dữ liệu mẫu và Timeline thành công (không trùng lặp)!');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Nhân viên: nhanvien@example.com / password');
    }
}
