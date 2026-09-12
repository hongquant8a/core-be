<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Thứ tự seed: quyền hạn → cấu hình hệ thống → dữ liệu mẫu.
     *
     * Tài khoản mẫu do TaskAssignmentDemoSeeder tạo:
     *  - admin / quandcore**11 — Super Admin
     *  - quanly1 / 123123 — Quản lý công việc
     *  - nhanvien1..nhanvien10 / 123123 — Nhân viên
     *  - truongphong1..truongphong3 / 123123 — Trưởng phòng (mỗi phòng ban một người)
     *
     * KHÔNG chạy mặc định — phải yêu cầu riêng:
     *  - TaiKhoanDangUyHoaKhanhSeeder: 38 tài khoản cán bộ Đảng ủy phường Hòa
     *    Khánh + 6 đơn vị. Là dữ liệu của một cơ quan cụ thể, không phải dữ
     *    liệu mẫu dùng chung, và có thể ghi đè tài khoản trùng username trên
     *    máy đã nhập dữ liệu từ hệ thống cũ.
     *
     *        sail artisan db:seed --class=TaiKhoanDangUyHoaKhanhSeeder
     */
    public function run(): void
    {
        // Quyền hạn trước, dữ liệu mẫu sau — seeder dữ liệu cần sẵn permission và vai trò.
        $this->call(PermissionSeeder::class);

        // Cấu hình hệ thống (không phải dữ liệu mẫu).
        $this->call(SettingSeeder::class);
        $this->call(NotificationEventConfigSeeder::class);
        $this->call(NotificationScheduleSeeder::class);

        // Dữ liệu mẫu phân hệ Quản lý công việc.
        $this->call(TaskAssignmentDemoSeeder::class);
    }
}
