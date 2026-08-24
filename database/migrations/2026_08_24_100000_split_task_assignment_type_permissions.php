<?php

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tách quyền danh mục Loại văn bản giao việc thành đủ 11 quyền riêng.
 *
 * Trước đây `show`/`stats` gộp vào `index`, `bulkDestroy` gộp vào `destroy`,
 * `bulkUpdateStatus`/`changeStatus` gộp vào `update`. Nay mỗi thao tác một quyền,
 * nên vai trò nào đang có quyền gộp phải được cấp thêm quyền tách ra tương ứng —
 * nếu không sẽ mất chức năng ngay sau khi deploy.
 */
return new class extends Migration
{
    protected const GUARD = 'web';

    /** Quyền đang có => các quyền mới cần cấp thêm. */
    protected const GRANTS = [
        'task-assignment-types.index' => [
            'task-assignment-types.show',
            'task-assignment-types.stats',
        ],
        'task-assignment-types.destroy' => [
            'task-assignment-types.bulkDestroy',
        ],
        'task-assignment-types.update' => [
            'task-assignment-types.bulkUpdateStatus',
            'task-assignment-types.changeStatus',
        ],
    ];

    public function up(): void
    {
        // 1. Dựng lại cây permission (tạo 5 quyền mới cho task-assignment-types).
        (new PermissionSeeder)->run();

        // 2. Cấp bù cho vai trò global + từng tổ chức.
        foreach (array_merge([null], Organization::pluck('id')->all()) as $orgId) {
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

                if ($granted === []) {
                    continue;
                }

                $existing = Permission::whereIn('name', array_keys($granted))
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
};
