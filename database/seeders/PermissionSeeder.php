<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserPreference;
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
        // Core - Nhật ký truy cập
        'log-activities' => [
            'stats', 'index', 'show', 'export', 'destroy', 'bulkDestroy',
            'destroyByDate', 'destroyAll',
        ],
        // TaskAssignment - Phòng ban giao việc
        'task-assignment-departments' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            'users', 'syncUsers', 'removeUser',
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
            'stats', 'statsByTime', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
        ],
        // TaskAssignment - Công việc
        'task-assignment-items' => [
            'stats', 'index', 'show', 'store', 'update', 'destroy',
            'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            'updateProgress', 'markDone',
            'statsByDepartment', 'statsByUser', 'statsByTime', 'overdue', 'upcomingDeadline',
            'statsByItemType', 'statsByDocument', 'exportMonthlyReport',
        ],
        // TaskAssignment - Báo cáo công việc
        'task-assignment-item-reports' => [
            'index', 'show', 'store', 'update', 'destroy', 'confirm',
        ],
        // TaskAssignment - Điều chuyển công việc
        'task-assignment-item-transfers' => [
            'index', 'store',
        ],
        // TaskAssignment - Ghi chú công việc
        'task-assignment-item-notes' => [
            'store',
        ],
        // Core - Cấu hình hệ thống
        'settings' => [
            'index', 'show', 'update',
        ],
        // Core - Cấu hình SSO (quản trị OAuth providers)
        'sso-settings' => [
            'index', 'update',
        ],
        // Core - Dashboard
        'dashboard' => [
            'index',
        ],
        // Core - Thông báo kiểm thử (SMS/Mail/Zalo)
        'notifications' => [
            'test',
        ],
        // Core - Cấu hình sự kiện thông báo
        'notifications.event-configs' => [
            'index', 'update',
        ],
        // Core - Cấu hình lịch nhắc
        'notifications.schedules' => [
            'index', 'store', 'update', 'destroy',
        ],
        // Core - Nhật ký gửi thông báo (admin)
        'notifications.logs' => [
            'index', 'show', 'destroy', 'bulkDestroy', 'export',
        ],
        // TaskAssignment - Công việc được giao (cho nhân viên xem task của mình)
        'my-received-tasks' => [
            'index',
        ],
        // TaskAssignment - Công việc đang giao (cho quản trị xem task mình đã giao)
        'my-assigned-tasks' => [
            'index',
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
        'log-activities' => 'Nhật ký truy cập',
        'settings' => 'Cấu hình hệ thống',
        'sso-settings' => 'Cấu hình SSO',
        'task-assignment-departments' => 'Phòng ban giao việc',
        'task-assignment-types' => 'Loại văn bản giao việc',
        'task-assignment-item-types' => 'Loại công việc',
        'task-assignment-documents' => 'Văn bản giao việc',
        'task-assignment-items' => 'Công việc',
        'task-assignment-item-reports' => 'Báo cáo công việc',
        'task-assignment-item-transfers' => 'Điều chuyển công việc',
        'task-assignment-item-notes' => 'Ghi chú công việc',
        'dashboard' => 'Tổng quan',
        'notifications' => 'Thông báo',
        'notifications.event-configs' => 'Cấu hình sự kiện thông báo',
        'notifications.schedules' => 'Cấu hình lịch nhắc',
        'notifications.logs' => 'Nhật ký gửi thông báo',
        'my-received-tasks' => 'Công việc được giao',
        'my-assigned-tasks' => 'Công việc đang giao',
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
        'destroyByDate' => 'Xóa theo khoảng thời gian',
        'destroyAll' => 'Xóa toàn bộ',
        'updateProgress' => 'Cập nhật tiến độ',
        'markDone' => 'Đánh dấu hoàn thành',
        'statsByDepartment' => 'Thống kê theo phòng ban',
        'statsByUser' => 'Thống kê theo người dùng',
        'statsByTime' => 'Thống kê theo thời gian',
        'overdue' => 'Danh sách quá hạn',
        'upcomingDeadline' => 'Danh sách sắp đến hạn',
        'statsByItemType' => 'Thống kê theo loại công việc',
        'statsByDocument' => 'Thống kê theo văn bản giao việc',
        'exportMonthlyReport' => 'Xuất báo cáo giao ban tháng',
        'users' => 'Danh sách người dùng',
        'syncUsers' => 'Đồng bộ người dùng',
        'removeUser' => 'Xóa người dùng',
        'confirm' => 'Xác nhận',
        'test' => 'Kiểm thử',
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

        $superAdminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
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

        // TaskAssignment test users
        $quanTriRole = Role::where('name', 'Quản trị')->where('guard_name', self::GUARD)->first();
        $truongPhongRole = Role::where('name', 'Trưởng phòng')->where('guard_name', self::GUARD)->first();
        $nhanVienRole = Role::where('name', 'Nhân viên')->where('guard_name', self::GUARD)->first();

        foreach ([
            ['email' => 'quantri@example.com', 'user_name' => 'quantri', 'name' => 'Quản trị', 'role' => $quanTriRole],
            ['email' => 'truongphong@example.com', 'user_name' => 'truongphong', 'name' => 'Trưởng Phòng', 'role' => $truongPhongRole],
            ['email' => 'nhanvien@example.com', 'user_name' => 'nhanvien', 'name' => 'Nhân viên', 'role' => $nhanVienRole],
        ] as $userData) {
            $user = User::updateOrCreate(
                [
                    'user_name' => $userData['user_name'],
                ],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => 'quandcore**11',
                    'status' => StatusEnum::Active->value,
                    'email_verified_at' => now(),
                ]
            );
            $user->forceFill([
                'created_by' => $superAdminUser->id,
                'updated_by' => $superAdminUser->id,
            ])->save();

            if ($userData['role']) {
                $user->syncRoles([$userData['role']]);
            }
        }

        // Gán tất cả user chưa có role vào org "Default" với role "Nhân viên"
        $allUsersWithoutRoles = User::whereDoesntHave('roles')->get();
        foreach ($allUsersWithoutRoles as $u) {
            if ($nhanVienRole) {
                $u->syncRoles([$nhanVienRole]);
            }
        }

        // Gán tất cả user vào organization "Default" qua user_preferences
        User::all()->each(function ($u) use ($defaultOrganization) {
            UserPreference::firstOrCreate(
                ['user_id' => $u->id],
                ['current_organization_id' => $defaultOrganization->id]
            );
        });
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

        // Thống kê nâng cao (giai đoạn 2)
        foreach (['statsByDepartment', 'statsByUser', 'statsByTime', 'overdue', 'upcomingDeadline', 'statsByItemType', 'statsByDocument', 'exportMonthlyReport'] as $action) {
            $names[] = "task-assignment-items.{$action}";
        }

        // Thống kê văn bản theo thời gian
        $names[] = 'task-assignment-documents.statsByTime';

        // Xem báo cáo
        $names[] = 'task-assignment-item-reports.index';
        $names[] = 'task-assignment-item-reports.show';

        // Điều chuyển & Ghi chú
        $names[] = 'task-assignment-item-transfers.index';
        $names[] = 'task-assignment-item-transfers.store';
        $names[] = 'task-assignment-item-notes.store';

        // Dashboard + 2 màn công việc cá nhân
        $names[] = 'dashboard.index';
        $names[] = 'my-assigned-tasks.index';
        $names[] = 'my-received-tasks.index';

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

            // Thống kê nâng cao (giai đoạn 2) - BE ép department_id phòng mình
            'task-assignment-items.statsByDepartment',
            'task-assignment-items.statsByUser',
            'task-assignment-items.statsByTime',
            'task-assignment-items.overdue',
            'task-assignment-items.upcomingDeadline',
            'task-assignment-items.statsByItemType',
            'task-assignment-items.statsByDocument',
            'task-assignment-items.exportMonthlyReport',
            'task-assignment-documents.statsByTime',

            // Báo cáo
            'task-assignment-item-reports.index',
            'task-assignment-item-reports.show',

            // Điều chuyển & Ghi chú
            'task-assignment-item-transfers.index',
            'task-assignment-item-transfers.store',
            'task-assignment-item-notes.store',

            // Dashboard + 2 màn công việc cá nhân
            'dashboard.index',
            'my-assigned-tasks.index',
            'my-received-tasks.index',
        ];
    }

    /** Permission cho Nhân viên: xem văn bản/công việc, cập nhật tiến độ, tạo/sửa báo cáo. */
    protected function getNhanVienPermissionNames(): array
    {
        return [
            // Phòng ban giao việc (để lấy danh sách user cho chức năng điều chuyển)
            'task-assignment-departments.index',
            'task-assignment-departments.users',

            // Công việc (xem + cập nhật tiến độ)
            'task-assignment-items.stats',
            'task-assignment-items.index',
            'task-assignment-items.show',
            'task-assignment-items.update',
            'task-assignment-items.updateProgress',
            'task-assignment-items.changeStatus',

            // Quá hạn + sắp đến hạn (giai đoạn 2) - BE ép department_id phòng mình
            'task-assignment-items.overdue',
            'task-assignment-items.upcomingDeadline',

            // Báo cáo (tạo, sửa, xem)
            'task-assignment-item-reports.index',
            'task-assignment-item-reports.show',
            'task-assignment-item-reports.store',
            'task-assignment-item-reports.update',

            // Điều chuyển & Ghi chú
            // 'task-assignment-item-transfers.index',
            'task-assignment-item-transfers.store',
            'task-assignment-item-notes.store',

            // Công việc được giao (xem task cua minh)
            'my-received-tasks.index',
        ];
    }
}
