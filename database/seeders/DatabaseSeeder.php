<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Thứ tự: User → Permission/Role/Team (phân quyền) → TaskAssignment → Setting.
     */
    public function run(): void
    {
        $this->seedUsers();
        $this->call(PermissionSeeder::class);
        $this->call(TaskAssignmentDataSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(NotificationEventConfigSeeder::class);
        $this->call(NotificationScheduleSeeder::class);
    }

    /**
     * Tạo 14 user Việt Nam với tên, email, username thực tế.
     * User đầu tiên (id=1) là Quản trị hệ thống.
     */
    protected function seedUsers(): void
    {
        $password = Hash::make('password');
        $baseDate = Carbon::parse('2026-01-10 08:00:00');

        // Lưu ý: PermissionSeeder sẽ tạo thêm admin, quantri, truongphong, nhanvien
        // nên ở đây chỉ tạo 10 user nghiệp vụ (tên Việt Nam)
        $users = [
            ['name' => 'Nguyễn Văn Hùng',   'email' => 'nvhung@snvdn.gov.vn',   'user_name' => 'nvhung',    'status' => 'active'],
            ['name' => 'Trần Thị Mai',       'email' => 'ttmai@snvdn.gov.vn',    'user_name' => 'ttmai',     'status' => 'active'],
            ['name' => 'Lê Hoàng Nam',       'email' => 'lhnam@snvdn.gov.vn',    'user_name' => 'lhnam',     'status' => 'active'],
            ['name' => 'Phạm Thị Hồng',     'email' => 'pthong@snvdn.gov.vn',   'user_name' => 'pthong',    'status' => 'active'],
            ['name' => 'Võ Đức Thắng',      'email' => 'vdthang@snvdn.gov.vn',  'user_name' => 'vdthang',   'status' => 'active'],
            ['name' => 'Huỳnh Thị Lan',     'email' => 'htlan@snvdn.gov.vn',    'user_name' => 'htlan',     'status' => 'active'],
            ['name' => 'Đặng Minh Tuấn',    'email' => 'dmtuan@snvdn.gov.vn',   'user_name' => 'dmtuan',    'status' => 'active'],
            ['name' => 'Bùi Thị Ngọc',      'email' => 'btngoc@snvdn.gov.vn',   'user_name' => 'btngoc',    'status' => 'active'],
            ['name' => 'Hoàng Văn Phúc',     'email' => 'hvphuc@snvdn.gov.vn',   'user_name' => 'hvphuc',    'status' => 'active'],
            ['name' => 'Ngô Thị Thanh',      'email' => 'ntthanh@snvdn.gov.vn',  'user_name' => 'ntthanh',   'status' => 'active'],
        ];

        foreach ($users as $i => $data) {
            $ts = $baseDate->copy()->addMinutes($i * 10);
            User::unguarded(fn () => User::firstOrCreate(
                ['user_name' => $data['user_name']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $password,
                    'email_verified_at' => $ts,
                    'status' => $data['status'],
                    'created_by' => 1,
                    'updated_by' => 1,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]
            )
            );
        }
    }
}
