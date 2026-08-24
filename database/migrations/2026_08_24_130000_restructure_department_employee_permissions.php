<?php

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Chuẩn hoá quyền hai danh mục Phòng ban và Nhân viên về đủ 11 quyền.
 *
 * Phòng ban: tách `stats`/`show` khỏi `index`, `bulkDestroy` khỏi `destroy`,
 * `bulkUpdateStatus`/`changeStatus` khỏi `update`; đồng thời BỎ 3 quyền quan hệ
 * `users` / `syncUsers` / `removeUser` — việc gán nhân viên nay là một trường của
 * chính form phòng ban (`employee_ids`), không còn endpoint riêng.
 *
 * Nhân viên: thêm `show` (trước dùng chung `index`).
 */
return new class extends Migration
{
    protected const GUARD = 'web';

    /** Quyền đang có => các quyền mới cần cấp thêm. */
    protected const GRANTS = [
        'task-assignment-departments.index' => [
            'task-assignment-departments.show',
            'task-assignment-departments.stats',
        ],
        'task-assignment-departments.destroy' => [
            'task-assignment-departments.bulkDestroy',
        ],
        'task-assignment-departments.update' => [
            'task-assignment-departments.bulkUpdateStatus',
            'task-assignment-departments.changeStatus',
        ],
        // Ai từng được đồng bộ nhân viên vào phòng ban thì nay dùng quyền sửa phòng ban.
        'task-assignment-departments.syncUsers' => [
            'task-assignment-departments.update',
        ],
        'task-assignment-departments.removeUser' => [
            'task-assignment-departments.update',
        ],
        'task-assignment-employees.index' => [
            'task-assignment-employees.show',
        ],
    ];

    public function up(): void
    {
        // Ghi lại quyền cũ TRƯỚC khi seeder xoá, để cấp bù sau.
        $plan = [];
        foreach ($this->organizationIds() as $orgId) {
            if ($orgId !== null) {
                setPermissionsTeamId($orgId);
            }

            foreach (Role::with('permissions:id,name')->get() as $role) {
                $granted = [];
                foreach ($role->permissions->pluck('name') as $name) {
                    foreach (self::GRANTS[$name] ?? [] as $new) {
                        $granted[$new] = true;
                    }
                }

                if ($granted !== []) {
                    $plan[$orgId ?? 'global'][$role->name] = array_keys($granted);
                }
            }
        }

        (new PermissionSeeder)->run();

        foreach ($plan as $orgKey => $roles) {
            if ($orgKey !== 'global') {
                setPermissionsTeamId((int) $orgKey);
            }

            foreach ($roles as $roleName => $names) {
                $role = Role::where('name', $roleName)->where('guard_name', self::GUARD)->first();
                if (! $role) {
                    continue;
                }

                $existing = Permission::whereIn('name', $names)
                    ->where('guard_name', self::GUARD)
                    ->pluck('name')
                    ->all();

                if ($existing !== []) {
                    $role->givePermissionTo($existing);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Không rollback: quyền tách ra là tập cha của quyền gộp cũ.
    }

    /** Vai trò global (null) + từng tổ chức. */
    protected function organizationIds(): array
    {
        return array_merge([null], Organization::pluck('id')->all());
    }
};
