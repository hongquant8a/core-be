<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;

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
    }

    /**
     * Tạo user. User đầu tiên (id=1) dùng làm người tạo/sửa cho dữ liệu mẫu.
     */
    protected function seedUsers(): void
    {
        User::factory(10)->create();

        // Gán created_by, updated_by (user 1 tự tham chiếu; các user khác tham chiếu user 1)
        User::where('id', 1)->update(['created_by' => 1, 'updated_by' => 1]);
        User::where('id', '>', 1)->update(['created_by' => 1, 'updated_by' => 1]);

    }
}
