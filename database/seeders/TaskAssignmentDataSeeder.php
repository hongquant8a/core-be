<?php

namespace Database\Seeders;

use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use Illuminate\Database\Seeder;

/**
 * Seed dữ liệu danh mục ban đầu cho module TaskAssignment:
 * - 7 phòng ban nội bộ
 * - 3 loại văn bản giao việc
 */
class TaskAssignmentDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedTypes();
    }

    protected function seedDepartments(): void
    {
        $departments = [
            ['code' => 'TTBCXB', 'name' => 'Phòng Tuyên truyền, Báo chí - Xuất bản', 'sort_order' => 1],
            ['code' => 'TTTH', 'name' => 'Phòng Thông tin - Tổng hợp', 'sort_order' => 2],
            ['code' => 'DVDTTG', 'name' => 'Phòng Dân vận các CQNN, dân tộc, tôn giáo', 'sort_order' => 3],
            ['code' => 'VP', 'name' => 'Văn phòng', 'sort_order' => 4],
            ['code' => 'DTCH', 'name' => 'Phòng đoàn thể và các hội', 'sort_order' => 5],
            ['code' => 'LLCTLSD', 'name' => 'Phòng Lý luận chính trị, lịch sử Đảng', 'sort_order' => 6],
            ['code' => 'KGVHVN', 'name' => 'Phòng Khoa giáo, Văn hoá - Văn nghệ', 'sort_order' => 7],
        ];

        foreach ($departments as $dept) {
            TaskAssignmentDepartment::firstOrCreate(
                ['code' => $dept['code']],
                [
                    'name' => $dept['name'],
                    'status' => 'active',
                    'sort_order' => $dept['sort_order'],
                ]
            );
        }
    }

    protected function seedTypes(): void
    {
        $types = [
            'Thường trực Thành ủy giao',
            'Công việc chuyên môn',
            'Công việc phát sinh',
        ];

        foreach ($types as $name) {
            TaskAssignmentType::firstOrCreate(
                ['name' => $name],
                ['status' => 'active']
            );
        }
    }
}
