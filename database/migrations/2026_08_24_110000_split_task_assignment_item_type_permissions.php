<?php

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tách quyền danh mục Loại công việc thành đủ 11 quyền riêng.
 *
 * Cùng khuôn với migration tách quyền Loại văn bản (2026_08_24_100000):
 * `show`/`stats` tách khỏi `index`, `bulkDestroy` tách khỏi `destroy`,
 * `bulkUpdateStatus`/`changeStatus` tách khỏi `update`. Vai trò đang có quyền gộp
 * phải được cấp thêm quyền tách ra, nếu không sẽ mất chức năng sau khi deploy.
 */
return new class extends Migration
{
    protected const GUARD = 'web';

    /** Quyền đang có => các quyền mới cần cấp thêm. */
    protected const GRANTS = [
        'task-assignment-item-types.index' => [
            'task-assignment-item-types.show',
            'task-assignment-item-types.stats',
        ],
        'task-assignment-item-types.destroy' => [
            'task-assignment-item-types.bulkDestroy',
        ],
        'task-assignment-item-types.update' => [
            'task-assignment-item-types.bulkUpdateStatus',
            'task-assignment-item-types.changeStatus',
        ],
    ];

    public function up(): void
    {
        // 1. Dựng lại cây permission (tạo 5 quyền mới cho task-assignment-item-types).
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
