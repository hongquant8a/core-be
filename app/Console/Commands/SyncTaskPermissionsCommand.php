<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class SyncTaskPermissionsCommand extends Command
{
    /**
     * Tên lệnh Artisan.
     *
     * @var string
     */
    protected $signature = 'permissions:sync-task-module';

    /**
     * Mô tả lệnh.
     *
     * @var string
     */
    protected $description = 'Đồng bộ và cập nhật mảng quyền Quản lý công việc (my-assigned-tasks, my-received-tasks) trên Production';

    public function handle(): int
    {
        $this->info('1. Đang cập nhật danh mục permission và các vai trò chuẩn...');
        
        // 1. Chạy PermissionSeeder để cập nhật cây permission và role chuẩn
        (new PermissionSeeder())->run();
        $this->info('-> Đã cập nhật xong cây Permission trong bảng permissions.');

        // 2. Đồng bộ các vai trò mở rộng / tùy chỉnh theo từng tổ chức (nếu có)
        $this->info('2. Đang kiểm tra và vá quyền cho các vai trò hiện có trong hệ thống...');
        
        $organizations = Organization::all();
        $assignedTaskPermissions = [
            'my-assigned-tasks.index',
            'my-assigned-tasks.pause',
            'my-assigned-tasks.cancel',
            'my-assigned-tasks.transfer',
            'my-assigned-tasks.markDone',
            'my-assigned-tasks.changeStatus',
            'my-assigned-tasks.note',
        ];

        $receivedTaskPermissions = [
            'my-received-tasks.index',
            'my-received-tasks.updateProgress',
            'my-received-tasks.report',
            'my-received-tasks.note',
            'my-received-tasks.transfer',
        ];

        $rolesCount = 0;

        // Xử lý vai trò không thuộc org (global) và từng org
        $orgIds = array_merge([null], $organizations->pluck('id')->all());

        foreach ($orgIds as $orgId) {
            if ($orgId !== null) {
                setPermissionsTeamId($orgId);
            }

            $roles = Role::all();
            foreach ($roles as $role) {
                $rolePermissionNames = $role->permissions->pluck('name')->toArray();

                // Kiểm tra nếu role có quyền xem Công việc đang giao hoặc từng có quyền pause cũ
                if (in_array('my-assigned-tasks.index', $rolePermissionNames) || in_array('task-assignment-items.pause', $rolePermissionNames)) {
                    $role->givePermissionTo($this->existingPermissions($assignedTaskPermissions));
                    $rolesCount++;
                }

                // Kiểm tra nếu role có quyền xem Công việc được giao hoặc từng có quyền updateProgress cũ
                if (in_array('my-received-tasks.index', $rolePermissionNames) || in_array('task-assignment-items.updateProgress', $rolePermissionNames)) {
                    $role->givePermissionTo($this->existingPermissions($receivedTaskPermissions));
                    $rolesCount++;
                }
            }
        }

        $this->info("-> Đã vá quyền thành công cho {$rolesCount} lượt vai trò.");

        // 3. Xóa cache Spatie Permission
        $this->info('3. Đang dọn dẹp cache permission...');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('-> Đã xóa cache Spatie permission thành công!');

        $this->newLine();
        $this->info('=== HOÀN THÀNH VÁ PERMISSION PRODUCTION THÀNH CÔNG ===');

        return self::SUCCESS;
    }

    /**
     * Lọc lấy các permission còn tồn tại trong DB.
     * Bộ quyền chuẩn có thể bị gộp/bỏ ở lần refactor sau (xem permissions:migrate-task-tree),
     * nên phải lọc để givePermissionTo không ném PermissionDoesNotExist khi chạy lại migration.
     */
    protected function existingPermissions(array $names): array
    {
        return Permission::whereIn('name', $names)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();
    }
}
