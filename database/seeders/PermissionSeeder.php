<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed permission, role, organization và phân quyền cho dự án.
 *
 * Khi thêm module mới hoặc thêm action (stats, index, show, store, ...) vào module,
 * bắt buộc cập nhật danh sách PERMISSIONS bên dưới cho đầy đủ, sau đó chạy lại seed.
 */
class PermissionSeeder extends Seeder
{
    /** Guard thống nhất cho Spatie (web + API Sanctum), tránh nhân đôi permission trong DB. */
    protected const GUARD = 'web';

    /**
     * Danh sách đầy đủ permission theo module và resource.
     * Định dạng: 'resource.action' — resource trùng prefix API (users, permissions, roles, organizations, posts, post-categories).
     * Khi thêm module/chức năng: bổ sung vào đúng nhóm và chạy sail artisan db:seed --class=PermissionSeeder.
     */
    protected static array $PERMISSIONS = [
        // Core - Users
        'users' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Core - Permissions (có description, sort_order, parent_id để nhóm frontend)
        'permissions' => [
            'stats', 'index', 'tree', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'export', 'import',
        ],
        // Core - Roles (bảng roles chuẩn Spatie, không có cột status)
        'roles' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'export', 'import',
        ],
        // Core - Organizations (cấu trúc cây parent_id)
        'organizations' => [
            'stats', 'index', 'tree', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Post - Bài viết
        'posts' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            'incrementView',
        ],
        // Post - Danh mục bài viết
        'post-categories' => [
            'stats', 'index', 'tree', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Core - Nhật ký truy cập
        'log-activities' => [
            'stats', 'index', 'show', 'export', 'destroy', 'bulkDestroy',
            'destroyByDate', 'destroyAll',
        ],
        // Document - Văn bản
        'documents' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Document - Loại văn bản
        'document-types' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Document - Cơ quan ban hành
        'issuing-agencies' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Document - Cấp ban hành
        'issuing-levels' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Document - Người ký
        'document-signers' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // Document - Lĩnh vực
        'document-fields' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // TaskAssignment - Phòng ban giao việc
        'task-assignment-departments' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // TaskAssignment - Loại văn bản giao việc
        'task-assignment-types' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // TaskAssignment - Loại công việc
        'task-assignment-item-types' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // TaskAssignment - Văn bản giao việc
        'task-assignment-documents' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // TaskAssignment - Công việc
        'task-assignment-items' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            'updateProgress',
        ],
        // TaskAssignment - Báo cáo công việc
        'task-assignment-item-reports' => [
            'index', 'show', 'store', 'update', 'destroy',
        ],
        // Core - Cấu hình hệ thống
        'settings' => [
            'index', 'show', 'update',
        ],
    ];

    public function run(): void
    {
        $this->migrateGuardApiToWeb();
        $this->seedOrganizations();
        $this->seedPermissions();
        $this->seedRoles();
        $this->assignPermissionsToRoles();
        $this->seedFixedUsersAndAssignRoles();
    }

    /** Chuyển permission/role từ guard api sang web (một lần khi đổi chiến lược guard). */
    protected function migrateGuardApiToWeb(): void
    {
        Permission::where('guard_name', 'api')->update(['guard_name' => 'web']);
        Role::where('guard_name', 'api')->update(['guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Tạo organization mặc định. */
    protected function seedOrganizations(): void
    {
        Organization::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default',
                'description' => 'Organization mặc định của hệ thống',
                'status' => StatusEnum::Active->value,
            ]
        );
    }

    /** Nhãn nhóm permission theo resource (để description). */
    protected static array $RESOURCE_LABELS = [
        'users' => 'Người dùng',
        'permissions' => 'Quyền',
        'roles' => 'Vai trò',
        'organizations' => 'Tổ chức',
        'posts' => 'Bài viết',
        'post-categories' => 'Danh mục bài viết',
        'log-activities' => 'Nhật ký truy cập',
        'documents' => 'Văn bản',
        'document-types' => 'Loại văn bản',
        'issuing-agencies' => 'Cơ quan ban hành',
        'issuing-levels' => 'Cấp ban hành',
        'document-signers' => 'Người ký',
        'document-fields' => 'Lĩnh vực',
        'settings' => 'Cấu hình hệ thống',
        'task-assignment-departments' => 'Phòng ban giao việc',
        'task-assignment-types' => 'Loại văn bản giao việc',
        'task-assignment-item-types' => 'Loại công việc',
        'task-assignment-documents' => 'Văn bản giao việc',
        'task-assignment-items' => 'Công việc',
        'task-assignment-item-reports' => 'Báo cáo công việc',
    ];

    /** Nhãn action (để description). */
    protected static array $ACTION_LABELS = [
        'stats' => 'Thống kê',
        'index' => 'Danh sách',
        'tree' => 'Cây',
        'show' => 'Chi tiết',
        'store' => 'Tạo mới',
        'update' => 'Cập nhật',
        'destroy' => 'Xóa',
        'bulkDestroy' => 'Xóa hàng loạt',
        'bulkUpdateStatus' => 'Cập nhật trạng thái hàng loạt',
        'changeStatus' => 'Đổi trạng thái',
        'export' => 'Xuất Excel',
        'import' => 'Nhập Excel',
        'incrementView' => 'Tăng lượt xem',
        'destroyByDate' => 'Xóa theo khoảng thời gian',
        'destroyAll' => 'Xóa toàn bộ',
        'updateProgress' => 'Cập nhật tiến độ',
    ];

    /** Tạo đầy đủ permission từ danh sách PERMISSIONS (kèm description, sort_order, parent_id). */
    protected function seedPermissions(): void
    {
        $sortOrder = 0;
        $parentIds = [];

        foreach (self::$PERMISSIONS as $resource => $actions) {
            $groupName = "group:{$resource}";
            $groupLabel = self::$RESOURCE_LABELS[$resource] ?? ucfirst($resource);
            $group = Permission::firstOrCreate(
                ['name' => $groupName, 'guard_name' => self::GUARD],
                ['name' => $groupName, 'guard_name' => self::GUARD, 'description' => $groupLabel, 'sort_order' => $sortOrder++, 'parent_id' => null]
            );
            $parentIds[$resource] = $group->id;

            foreach ($actions as $idx => $action) {
                $name = "{$resource}.{$action}";
                $actionLabel = self::$ACTION_LABELS[$action] ?? $action;
                $desc = ($groupLabel ?? '').' - '.$actionLabel;
                Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => self::GUARD],
                    ['description' => $desc, 'sort_order' => $idx, 'parent_id' => $group->id]
                );
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Tạo các role mặc định. */
    protected function seedRoles(): void
    {
        // Role global: không gắn organization_id trực tiếp trên bảng roles.
        Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Editor', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Vai trò mẫu', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );

        // TaskAssignment - 3 role nghiệp vụ giao việc
        Role::firstOrCreate(
            ['name' => 'Quản trị', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Trưởng phòng', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Nhân viên', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );

        // Chuẩn hóa dữ liệu cũ nếu còn role theo organization.
        Role::query()->update(['organization_id' => null]);
    }

    /** Gán permission cho từng role. */
    protected function assignPermissionsToRoles(): void
    {
        $allPermissionNames = $this->getAllPermissionNames();
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', self::GUARD)->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions($allPermissionNames);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', self::GUARD)->first();
        if ($admin) {
            $admin->syncPermissions($allPermissionNames);
        }

        $editorPermissionNames = $this->getEditorPermissionNames();
        $editor = Role::where('name', 'Editor')->where('guard_name', self::GUARD)->first();
        if ($editor) {
            $editor->syncPermissions($editorPermissionNames);
        }

        $samplePermissionNames = $this->getSamplePermissionNames();
        $sampleRole = Role::where('name', 'Vai trò mẫu')->where('guard_name', self::GUARD)->first();
        if ($sampleRole) {
            $sampleRole->syncPermissions($samplePermissionNames);
        }

        // TaskAssignment roles
        $quanTriRole = Role::where('name', 'Quản trị')->where('guard_name', self::GUARD)->first();
        if ($quanTriRole) {
            $quanTriRole->syncPermissions($this->getQuanTriPermissionNames());
        }

        $truongPhongRole = Role::where('name', 'Trưởng phòng')->where('guard_name', self::GUARD)->first();
        if ($truongPhongRole) {
            $truongPhongRole->syncPermissions($this->getTruongPhongPermissionNames());
        }

        $nhanVienRole = Role::where('name', 'Nhân viên')->where('guard_name', self::GUARD)->first();
        if ($nhanVienRole) {
            $nhanVienRole->syncPermissions($this->getNhanVienPermissionNames());
        }
    }

    /**
     * Tạo user cố định để đăng nhập kiểm tra và gán role:
     * - admin@example.com => Super Admin
     * - basic@example.com => Vai trò mẫu (quyền cơ bản)
     */
    protected function seedFixedUsersAndAssignRoles(): void
    {
        $defaultOrganization = Organization::where('slug', 'default')->first();
        if (! $defaultOrganization) {
            return;
        }
        setPermissionsTeamId($defaultOrganization->id);

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', self::GUARD)->first();
        $sampleRole = Role::where('name', 'Vai trò mẫu')->where('guard_name', self::GUARD)->first();

        $superAdminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'admin',
                'user_name' => 'admin',
                'password' => 'quandcore**11',
                'status' => StatusEnum::Active->value,
                'email_verified_at' => now(),
            ]
        );
        $superAdminUser->forceFill([
            'created_by' => $superAdminUser->id,
            'updated_by' => $superAdminUser->id,
        ])->save();

        if ($superAdmin) {
            $superAdminUser->syncRoles([$superAdmin]);
        }

        $basicUser = User::updateOrCreate(
            ['email' => 'basic@example.com'],
            [
                'name' => 'basic',
                'user_name' => 'basic',
                'password' => 'quandcore**11',
                'status' => StatusEnum::Active->value,
                'email_verified_at' => now(),
            ]
        );
        $basicUser->forceFill([
            'created_by' => $superAdminUser->id,
            'updated_by' => $superAdminUser->id,
        ])->save();

        if ($sampleRole) {
            $basicUser->syncRoles([$sampleRole]);
        }
    }

    /** Lấy toàn bộ tên permission (resource.action). */
    protected function getAllPermissionNames(): array
    {
        $names = [];
        foreach (self::$PERMISSIONS as $resource => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        return $names;
    }

    /** Permission cho role Editor: chỉ posts và post-categories. */
    protected function getEditorPermissionNames(): array
    {
        $names = [];
        foreach (['posts' => self::$PERMISSIONS['posts'], 'post-categories' => self::$PERMISSIONS['post-categories']] as $resource => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        return $names;
    }

    /** Permission cho Vai trò mẫu: chỉ xem bài viết và danh mục (index, show, tree, stats, incrementView). */
    protected function getSamplePermissionNames(): array
    {
        return [
            'posts.stats',
            'posts.index',
            'posts.show',
            'posts.incrementView',
            'post-categories.stats',
            'post-categories.index',
            'post-categories.tree',
            'post-categories.show',
        ];
    }

    /** Permission cho Quản trị: full danh mục + xem/thống kê/export văn bản, công việc, báo cáo. */
    protected function getQuanTriPermissionNames(): array
    {
        $names = [];

        // Full quyền trên danh mục
        foreach (['task-assignment-departments', 'task-assignment-types', 'task-assignment-item-types'] as $resource) {
            foreach (self::$PERMISSIONS[$resource] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        // Xem + thống kê + export văn bản và công việc
        foreach (['task-assignment-documents', 'task-assignment-items'] as $resource) {
            foreach (['stats', 'index', 'show', 'export'] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        // Xem báo cáo
        $names[] = 'task-assignment-item-reports.index';
        $names[] = 'task-assignment-item-reports.show';

        return $names;
    }

    /** Permission cho Trưởng phòng: tạo/sửa văn bản, công việc, assign, xem báo cáo. */
    protected function getTruongPhongPermissionNames(): array
    {
        return [
            // Văn bản giao việc
            'task-assignment-documents.stats',
            'task-assignment-documents.index',
            'task-assignment-documents.show',
            'task-assignment-documents.store',
            'task-assignment-documents.update',
            'task-assignment-documents.changeStatus',

            // Công việc
            'task-assignment-items.stats',
            'task-assignment-items.index',
            'task-assignment-items.show',
            'task-assignment-items.store',
            'task-assignment-items.update',
            'task-assignment-items.changeStatus',
            'task-assignment-items.updateProgress',

            // Báo cáo
            'task-assignment-item-reports.index',
            'task-assignment-item-reports.show',
        ];
    }

    /** Permission cho Nhân viên: xem văn bản/công việc, cập nhật tiến độ, tạo/sửa báo cáo. */
    protected function getNhanVienPermissionNames(): array
    {
        return [
            // Văn bản giao việc (chỉ xem)
            'task-assignment-documents.index',
            'task-assignment-documents.show',

            // Công việc (xem + cập nhật tiến độ)
            'task-assignment-items.index',
            'task-assignment-items.show',
            'task-assignment-items.updateProgress',

            // Báo cáo (tạo, sửa, xem)
            'task-assignment-item-reports.index',
            'task-assignment-item-reports.show',
            'task-assignment-item-reports.store',
            'task-assignment-item-reports.update',
        ];
    }
}
