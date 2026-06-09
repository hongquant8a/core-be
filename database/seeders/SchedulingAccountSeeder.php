<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use Illuminate\Database\Seeder;

class SchedulingAccountSeeder extends Seeder
{
    protected const GUARD = 'web';
    protected const PASSWORD = 'quandcore**11';

    protected array $roles = [];

    public function run(): void
    {
        $org = Organization::where('slug', 'default')->first();
        if (! $org) {
            $this->command->error('Chưa có organization "default". Chạy PermissionSeeder trước.');
            return;
        }

        $this->createRoles();
        $this->createAccounts($org);
        $this->command->info('Đã tạo xong tài khoản & nhân viên cho module Lịch công tác.');
    }

    protected function createRoles(): void
    {
        $definitions = [
            'LT - Thư ký' => $this->ltThuKyPermissions(),
            'LT - Lãnh đạo' => $this->ltLanhDaoPermissions(),
            'LT - Lái xe' => $this->ltLaiXePermissions(),
            'VP - Nhân viên' => $this->vpNhanVienPermissions(),
            'VP - Tổng hợp' => $this->vpTongHopPermissions(),
            'VP - Lãnh đạo' => $this->vpLanhDaoPermissions(),
            'VP - Lái xe' => $this->vpLaiXePermissions(),
            'QL Lịch công tác' => $this->qlLichCongTacPermissions(),
        ];

        foreach ($definitions as $name => $perms) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['organization_id' => null],
            );
            $role->syncPermissions($perms);
            $this->roles[$name] = $role;
        }
    }

    protected function createAccounts(Organization $org): void
    {
        $accounts = [
            ['email' => 'lt_thuky@example.com', 'user_name' => 'lt_thuky', 'name' => 'LT - Thư ký', 'role' => 'LT - Thư ký', 'position' => 'Thư ký'],
            ['email' => 'lt_lanhdao@example.com', 'user_name' => 'lt_lanhdao', 'name' => 'LT - Lãnh đạo', 'role' => 'LT - Lãnh đạo', 'position' => 'Lãnh đạo'],
            ['email' => 'lt_laixe@example.com', 'user_name' => 'lt_laixe', 'name' => 'LT - Lái xe', 'role' => 'LT - Lái xe', 'position' => 'Lái xe'],
            ['email' => 'vp_nhanvien@example.com', 'user_name' => 'vp_nhanvien', 'name' => 'VP - Nhân viên', 'role' => 'VP - Nhân viên', 'position' => 'Nhân viên'],
            ['email' => 'vp_tonghop@example.com', 'user_name' => 'vp_tonghop', 'name' => 'VP - Tổng hợp', 'role' => 'VP - Tổng hợp', 'position' => 'Tổng hợp'],
            ['email' => 'vp_lanhdao@example.com', 'user_name' => 'vp_lanhdao', 'name' => 'VP - Lãnh đạo', 'role' => 'VP - Lãnh đạo', 'position' => 'Lãnh đạo'],
            ['email' => 'vp_laixe@example.com', 'user_name' => 'vp_laixe', 'name' => 'VP - Lái xe', 'role' => 'VP - Lái xe', 'position' => 'Lái xe'],
            ['email' => 'ql_lichcongtac@example.com', 'user_name' => 'ql_lichcongtac', 'name' => 'QL Lịch công tác', 'role' => 'QL Lịch công tác', 'position' => 'Quản trị'],
        ];

        $firstUserId = User::where('user_name', 'admin')->value('id') ?? 1;

        foreach ($accounts as $data) {
            $user = User::where('user_name', $data['user_name'])
                ->orWhere('email', $data['email'])
                ->first();

            if (! $user) {
                $user = User::create([
                    'email' => $data['email'],
                    'user_name' => $data['user_name'],
                    'name' => $data['name'],
                    'password' => self::PASSWORD,
                    'status' => StatusEnum::Active->value,
                    'email_verified_at' => now(),
                ]);
            }

            $user->forceFill([
                'created_by' => $firstUserId,
                'updated_by' => $firstUserId,
            ])->save();

            $role = $this->roles[$data['role']] ?? null;
            if ($role) {
                $user->syncRoles([$role]);
            }

            // Thêm vào danh sách nhân viên lịch công tác
            SchedulingEmployee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'organization_id' => $org->id,
                    'name' => $data['name'],
                    'position_name' => $data['position'],
                    'email' => $data['email'],
                    'status' => 'active',
                    'sort_order' => 0,
                ],
            );
        }
    }

    // ──── Permission maps ────────────────────────────────────────────

    protected function ltThuKyPermissions(): array
    {
        return [
            'schedules-executive.stats',
            'schedules-executive.index',
            'schedules-executive.show',
            'schedules-executive.store',
            'schedules-executive.update',
            'schedules-executive.destroy',
            'schedules-executive.bulkDestroy',
            'schedules-executive.bulkUpdateStatus',
            'schedules-executive.changeStatus',
            'schedules-executive.export',
            'schedules-executive.duplicate',
            'schedules-executive.reorder',
            'schedules-executive.home',
        ];
    }

    protected function ltLanhDaoPermissions(): array
    {
        return [
            'schedules-executive.stats',
            'schedules-executive.index',
            'schedules-executive.show',
            'schedules-executive.export',
            'schedules-executive.home',
        ];
    }

    protected function ltLaiXePermissions(): array
    {
        return [
            'schedules-executive.driver-view',
            'schedules-executive.home',
        ];
    }

    protected function vpNhanVienPermissions(): array
    {
        return [
            'schedules-office.stats',
            'schedules-office.index',
            'schedules-office.show',
            'schedules-office.store',
            'schedules-office.update',
            'schedules-office.destroy',
            'schedules-office.bulkDestroy',
            'schedules-office.bulkUpdateStatus',
            'schedules-office.changeStatus',
            'schedules-office.export',
            'schedules-office.duplicate',
            'schedules-office.reorder',
            'schedules-office.home',
        ];
    }

    protected function vpTongHopPermissions(): array
    {
        return [
            'schedules-office.stats',
            'schedules-office.index',
            'schedules-office.show',
            'schedules-office.approve',
            'schedules-office.home',
        ];
    }

    protected function vpLanhDaoPermissions(): array
    {
        return [
            'schedules-office.stats',
            'schedules-office.index',
            'schedules-office.show',
            'schedules-office.export',
            'schedules-office.home',
        ];
    }

    protected function vpLaiXePermissions(): array
    {
        return [
            'schedules-office.driver-view',
            'schedules-office.home',
        ];
    }

    protected function qlLichCongTacPermissions(): array
    {
        return [
            // schedules-executive
            'schedules-executive.stats',
            'schedules-executive.index',
            'schedules-executive.show',
            'schedules-executive.store',
            'schedules-executive.update',
            'schedules-executive.destroy',
            'schedules-executive.bulkDestroy',
            'schedules-executive.bulkUpdateStatus',
            'schedules-executive.changeStatus',
            'schedules-executive.export',
            'schedules-executive.approve',
            'schedules-executive.duplicate',
            'schedules-executive.reorder',
            'schedules-executive.driver-view',
            'schedules-executive.home',
            // schedules-office
            'schedules-office.stats',
            'schedules-office.index',
            'schedules-office.show',
            'schedules-office.store',
            'schedules-office.update',
            'schedules-office.destroy',
            'schedules-office.bulkDestroy',
            'schedules-office.bulkUpdateStatus',
            'schedules-office.changeStatus',
            'schedules-office.export',
            'schedules-office.approve',
            'schedules-office.duplicate',
            'schedules-office.reorder',
            'schedules-office.driver-view',
            'schedules-office.home',
            // scheduling-employees
            'scheduling-employees.stats',
            'scheduling-employees.index',
            'scheduling-employees.show',
            'scheduling-employees.store',
            'scheduling-employees.update',
            'scheduling-employees.destroy',
            'scheduling-employees.bulkDestroy',
            'scheduling-employees.bulkUpdateStatus',
            'scheduling-employees.changeStatus',
            'scheduling-employees.export',
            'scheduling-employees.import',
            // scheduling-employee-groups
            'scheduling-employee-groups.stats',
            'scheduling-employee-groups.index',
            'scheduling-employee-groups.show',
            'scheduling-employee-groups.store',
            'scheduling-employee-groups.update',
            'scheduling-employee-groups.destroy',
            'scheduling-employee-groups.bulkDestroy',
            'scheduling-employee-groups.bulkUpdateStatus',
            'scheduling-employee-groups.changeStatus',
            'scheduling-employee-groups.export',
            // scheduling-settings
            'scheduling-settings.show',
            'scheduling-settings.update',
        ];
    }
}
