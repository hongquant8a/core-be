<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Enums\ScheduleStatus;
use Carbon\Carbon;

class SchedulingModuleSeeder extends Seeder
{
    public function run(): void
    {
        $orgId = 1;

        // 1. Get some users
        $users = User::take(5)->get();
        if ($users->count() < 2) {
            $this->command->info('Not enough users to seed. Run UserSeeder first.');
            return;
        }

        // 2. Create Employee Groups
        $groupA = SchedulingEmployeeGroup::firstOrCreate(
            ['name' => 'Ban Giám Đốc'],
            [
                'organization_id' => $orgId,
                'description' => 'Nhóm các thành viên Ban Giám Đốc',
                'status' => 'active',
                'sort_order' => 1
            ]
        );

        $groupB = SchedulingEmployeeGroup::firstOrCreate(
            ['name' => 'Trưởng Phòng Ban'],
            [
                'organization_id' => $orgId,
                'description' => 'Nhóm trưởng các phòng ban chuyên môn',
                'status' => 'active',
                'sort_order' => 2
            ]
        );

        // 3. Create Scheduling Employees
        $employees = [];
        $positions = ['Giám đốc', 'Phó Giám đốc', 'Trưởng phòng TC-HC', 'Trưởng phòng Kỹ thuật', 'Chuyên viên'];
        foreach ($users as $index => $user) {
            $employees[] = SchedulingEmployee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'organization_id' => $orgId,
                    'name' => $user->name,
                    'position_name' => $positions[$index] ?? 'Chuyên viên',
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => 'active',
                    'sort_order' => $index + 1
                ]
            );
        }

        // Attach employees to groups
        if (isset($employees[0])) {
            $employees[0]->groups()->syncWithoutDetaching([$groupA->id]);
        }
        if (isset($employees[1])) {
            $employees[1]->groups()->syncWithoutDetaching([$groupA->id, $groupB->id]);
        }
        if (isset($employees[2])) {
            $employees[2]->groups()->syncWithoutDetaching([$groupB->id]);
        }

        // 4. Create sample schedules (idempotent — skip nếu đã có dữ liệu)
        if (Schedule::count() > 0) {
            $this->command->info('Schedules already seeded, skipping.');
            return;
        }

        $startOfWeek = Carbon::now()->startOfWeek();
        $dateCol = Schedule::dateColumn();

        $schedulesData = [
            [
                'content' => 'Họp giao ban đầu tuần',
                'location' => 'Phòng họp số 1',
                'session' => 'S',
                $dateCol => $startOfWeek->copy()->addDays(0)->setHour(8)->setMinute(0),
                'status' => ScheduleStatus::PUBLISHED->value,
                'host_id' => $employees[0]->user_id,
                'preparation_unit' => 'Phòng TC-HC',
                'participants_text' => 'Toàn thể CB-CNV',
                'module_type' => 'EXECUTIVE',
                'sort_order' => 1,
            ],
            [
                'content' => 'Tiếp đón đoàn khách đối tác chiến lược',
                'location' => 'Phòng khách VIP',
                'session' => 'C',
                $dateCol => $startOfWeek->copy()->addDays(1)->setHour(14)->setMinute(30),
                'status'         => ScheduleStatus::DRAFT->value,
                'approval_status' => \App\Modules\Scheduling\Enums\ApprovalStatus::PENDING->value,
                'host_id' => $employees[0]->user_id,
                'preparation_unit' => 'Phòng Kinh doanh',
                'participants_text' => 'Ban Giám đốc, Trưởng phòng Kinh doanh',
                'module_type' => 'EXECUTIVE',
                'sort_order' => 2,
            ],
            [
                'content' => 'Họp chuyên đề kỹ thuật tháng',
                'location' => 'Phòng họp số 2',
                'session' => 'S',
                $dateCol => $startOfWeek->copy()->addDays(2)->setHour(9)->setMinute(0),
                'status' => ScheduleStatus::PUBLISHED->value,
                'host_id' => $employees[1]->user_id,
                'preparation_unit' => 'Phòng Kỹ thuật',
                'participants_text' => 'Các chuyên viên phòng Kỹ thuật',
                'module_type' => 'OFFICE',
                'sort_order' => 1,
            ],
            [
                'content' => 'Kiểm tra tiến độ dự án Alpha',
                'location' => 'Công trường dự án',
                'session' => 'C',
                $dateCol => $startOfWeek->copy()->addDays(3)->setHour(15)->setMinute(0),
                'status' => ScheduleStatus::DRAFT->value,
                'host_id' => $employees[1]->user_id,
                'driver_text' => 'Nguyễn Văn Tài',
                'preparation_unit' => 'Ban QLDA',
                'participants_text' => 'Đội thi công',
                'module_type' => 'OFFICE',
                'sort_order' => 2,
            ],
            [
                'content' => 'Lễ kỷ niệm thành lập công ty',
                'location' => 'Hội trường lớn',
                'session' => 'T',
                $dateCol => $startOfWeek->copy()->addDays(4)->setHour(18)->setMinute(0),
                'status' => ScheduleStatus::PUBLISHED->value,
                'host_id' => $employees[0]->user_id,
                'preparation_unit' => 'Công đoàn',
                'participants_text' => 'Tất cả nhân viên',
                'module_type' => 'EXECUTIVE',
                'sort_order' => 3,
            ],
        ];

        foreach ($schedulesData as $item) {
            $item['organization_id'] = $orgId;
            $item['created_by'] = $users[0]->id;
            Schedule::create($item);
        }

        $this->command->info('Scheduling module seeded successfully!');
    }
}
