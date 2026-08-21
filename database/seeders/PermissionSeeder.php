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
     * Danh sách đầy đủ permission theo nhóm module (Core, TaskAssignment, Meeting, Scheduling).
     * Định dạng: 'Module' => ['resource' => ['action', ...]] — resource trùng prefix API.
     * Khi thêm module/chức năng: bổ sung vào đúng nhóm và chạy sail artisan db:seed --class=PermissionSeeder.
     */
    protected static array $PERMISSIONS = [
        'Core' => [
            'users' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'permissions' => [
                'stats', 'index', 'tree', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'export', 'import',
            ],
            'roles' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'export', 'import',
            ],
            'organizations' => [
                'stats', 'index', 'tree', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'log-activities' => [
                'stats', 'index', 'show', 'export', 'destroy', 'bulkDestroy',
                'destroyByDate', 'destroyAll',
            ],
            'settings' => [
                'index', 'show', 'update',
            ],
            'sso-settings' => [
                'index', 'update',
            ],
            'dashboard' => [
                'systemOverview',
            ],
            'notifications' => [
                'test',
            ],
            'notifications.event-configs' => [
                'index', 'update',
            ],
            'notifications.schedules' => [
                'index', 'store', 'update', 'destroy',
            ],
            'notifications.logs' => [
                'index', 'show', 'destroy', 'bulkDestroy', 'export',
            ],
            'notifications.templates' => [
                'index', 'store', 'update', 'destroy', 'variables',
            ],
        ],
        // TaskAssignment — thứ tự resource theo đúng sidebar core-fe.
        'TaskAssignment' => [
            'task-overview' => [
                'index', 'exportMonthlyReport',
            ],
            // Công việc chỉ được tạo/sửa/xóa trong màn Văn bản giao việc (core-fe chặn
            // giao đột xuất ngoài luồng) → 3 action *Item nằm cùng nhóm văn bản.
            'task-assignment-documents' => [
                'index', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'export',
                'storeItem', 'updateItem', 'destroyItem',
            ],
            'my-assigned-tasks' => [
                'index', 'export', 'pause', 'cancel', 'transfer', 'markDone', 'changeStatus', 'note',
            ],
            'my-received-tasks' => [
                'index', 'export', 'updateProgress', 'report', 'note', 'transfer',
            ],
            'presentation' => [
                'index',
            ],
            'task-assignment-petitions' => [
                'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'manage',
            ],
            'task-assignment-types' => [
                'index', 'store', 'update', 'destroy', 'export', 'import',
            ],
            'task-assignment-item-types' => [
                'index', 'store', 'update', 'destroy', 'export', 'import',
            ],
            'task-assignment-departments' => [
                'index', 'store', 'update', 'destroy', 'export', 'import',
                'users', 'syncUsers', 'removeUser',
            ],
            'task-assignment-employees' => [
                'index', 'stats', 'store', 'update', 'destroy', 'export', 'import',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus',
            ],
        ],
        'Meeting' => [
            'meetings' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export',
                'exportReports', 'home',
            ],
            'meeting-types' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'meeting-locations' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'meeting-document-types' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'meeting-attendee-groups' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
                'attendees',
            ],
            'meeting-attendees' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'meeting-agendas' => [
                'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy',
            ],
            'meeting-documents' => [
                'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy',
            ],
            'meeting-participants' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy',
            ],
            'meeting-vote-topics' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy',
            ],
            'meeting-minutes-templates' => [
                'index', 'show', 'store', 'update', 'destroy',
            ],
            'meeting-invitation-templates' => [
                'index', 'show', 'store', 'update', 'destroy',
            ],
            'meeting-settings' => [
                'show', 'update',
            ],
        ],
        'Scheduling' => [
            'schedules-executive' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export',
                'approve', 'duplicate', 'reorder', 'driver-view', 'home',
            ],
            'schedules-office' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export',
                'approve', 'duplicate', 'reorder', 'driver-view', 'home',
            ],
            'scheduling-employees' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'scheduling-employee-groups' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export',
            ],
            'scheduling-settings' => [
                'show', 'update',
            ],
        ],
    ];

    /**
     * Permission đã bị gộp/bỏ khi refactor cây theo nav — xóa khỏi DB mỗi lần seed.
     * Quy tắc gộp: `.show`/`.stats` → `.index`; `.bulkDestroy` → `.destroy`;
     * `.bulkUpdateStatus`/`.changeStatus` → `.update`; thống kê dashboard → `task-overview.index`.
     */
    protected static array $REMOVED_PERMISSIONS = [
        // Nhóm task-assignment-items bị bỏ hẳn: CRUD công việc về Văn bản giao việc,
        // thao tác riêng về 2 màn cá nhân, thống kê về task-overview.
        'task-assignment-items' => [
            'index', 'show', 'store', 'update', 'destroy', 'export',
            'stats', 'changeStatus', 'bulkDestroy', 'bulkUpdateStatus',
            'pause', 'cancel', 'updateProgress', 'markDone',
            'statsByDepartment', 'statsByUser', 'statsByTime', 'overdue', 'upcomingDeadline',
            'statsByItemType', 'statsByDocument', 'exportMonthlyReport',
        ],
        'task-assignment-documents' => ['stats', 'statsByTime', 'show', 'changeStatus'],
        'task-assignment-departments' => ['stats', 'show', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus'],
        'task-assignment-employees' => ['show'],
        'task-assignment-types' => ['stats', 'show', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus'],
        'task-assignment-item-types' => ['stats', 'show', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus'],
        'task-assignment-petitions' => ['stats'],
        'my-assigned-tasks' => ['show'],
        'my-received-tasks' => ['show'],
    ];

    /** Nhóm (tầng group) không còn dùng — xóa để cây không còn nhánh rỗng. */
    protected static array $REMOVED_GROUPS = [
        'section:task-tracking',
        'section:task-catalog',
        'group:task-assignment-items',
        'group:task-assignment-item-reports',
        'group:task-assignment-item-transfers',
        'group:task-assignment-item-notes',
    ];

    /** Trả về danh sách permission dạng phẳng [resource => actions] từ cấu trúc module. */
    private static function getFlatPermissions(): array
    {
        $flat = [];
        foreach (self::$PERMISSIONS as $resources) {
            foreach ($resources as $resource => $actions) {
                $flat[$resource] = $actions;
            }
        }
        return $flat;
    }

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

    /** Nhãn tiếng Việt cho module. */
    protected static array $MODULE_LABELS = [
        'Core'           => 'Hệ thống',
        'TaskAssignment' => 'Quản lý công việc',
        'Meeting'        => 'Phòng họp không giấy',
        'Scheduling'     => 'Lịch công tác',
    ];

    /** Nhãn nhóm permission theo resource (để description). */
    protected static array $RESOURCE_LABELS = [
        'users' => 'Người dùng',
        'permissions' => 'Quyền',
        'roles' => 'Vai trò',
        'organizations' => 'Tổ chức',
        'log-activities' => 'Nhật ký truy cập',
        'settings' => 'Cấu hình hệ thống',
        'sso-settings' => 'Cấu hình SSO',
        'task-assignment-departments' => 'Danh mục phòng ban',
        'task-assignment-employees' => 'Danh mục nhân viên',
        'task-assignment-types' => 'Danh mục loại văn bản',
        'task-assignment-item-types' => 'Danh mục loại công việc',
        'task-assignment-documents' => 'Văn bản giao việc',
        'task-overview' => 'Tổng quan công việc',
        'task-assignment-petitions' => 'Quản lý đơn thư',
        'presentation' => 'Trình diễn công việc',
        'dashboard' => 'Tổng quan',
        'notifications' => 'Thông báo',
        'notifications.event-configs' => 'Cấu hình sự kiện thông báo',
        'notifications.schedules' => 'Cấu hình lịch nhắc',
        'notifications.logs' => 'Nhật ký gửi thông báo',
        'notifications.templates' => 'Cấu hình ZNS template thông báo',
        'my-received-tasks' => 'Công việc được giao',
        'my-assigned-tasks' => 'Công việc đang giao',
        'meetings' => 'Cuộc họp',
        'meeting-types' => 'Danh mục loại cuộc họp',
        'meeting-locations' => 'Danh mục địa điểm họp',
        'meeting-document-types' => 'Danh mục loại tài liệu họp',
        'meeting-attendee-groups' => 'Danh mục nhóm đại biểu họp',
        'meeting-attendees' => 'Danh mục đại biểu họp',
        'meeting-agendas' => 'Chương trình họp',
        'meeting-documents' => 'Tài liệu họp',
        'meeting-participants' => 'Người tham dự họp',
        'meeting-vote-topics' => 'Chương trình biểu quyết',
        'meeting-minutes-templates' => 'Danh mục template biên bản họp',
        'meeting-invitation-templates' => 'Danh mục template giấy mời họp',
        'meeting-settings' => 'Cấu hình cuộc họp',
        'schedules-executive' => 'Lịch công tác - Thường trực',
        'schedules-office'    => 'Lịch công tác - Văn phòng',
        'scheduling-employees' => 'Danh mục nhân viên lịch công tác',
        'scheduling-employee-groups' => 'Danh mục nhóm nhân viên lịch công tác',
        'scheduling-settings' => 'Cấu hình lịch công tác',
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
        'home' => 'Truy cập trang chủ',
        'destroyByDate' => 'Xóa theo khoảng thời gian',
        'destroyAll' => 'Xóa toàn bộ',
        'updateProgress' => 'Cập nhật tiến độ',
        'markDone' => 'Đánh dấu hoàn thành',
        'exportMonthlyReport' => 'Xuất báo cáo giao ban tháng',
        'storeItem' => 'Thêm công việc',
        'updateItem' => 'Sửa công việc',
        'destroyItem' => 'Xóa công việc',
        'exportReports' => 'Xuất báo cáo tổng hợp cuộc họp',
        'users' => 'Danh sách người dùng',
        'syncUsers' => 'Đồng bộ người dùng',
        'removeUser' => 'Xóa người dùng',
        'confirm' => 'Xác nhận',
        'test' => 'Kiểm thử',
        'complete' => 'Đánh dấu hoàn thành',
        'approve' => 'Duyệt',
        'reject' => 'Từ chối',
        'attendees' => 'Quản lý đại biểu trong nhóm',
        'systemOverview' => 'Tổng quan hệ thống',
        'reorder' => 'Sắp xếp lại',
        'duplicate' => 'Sao chép',
        'driver-view' => 'Xem lịch phân công lái xe',
        'pause' => 'Tạm dừng',
        'cancel' => 'Hủy',
        'manage' => 'Quản lý (Mở khóa)',
        'transfer' => 'Điều chuyển công việc',
        'report' => 'Báo cáo công việc',
        'note' => 'Ghi chú công việc',
    ];

    /** Tạo đầy đủ permission từ danh sách PERMISSIONS (kèm description, sort_order, parent_id).
     * Cây 3 tầng: module → group:resource → resource.action. */
    protected function seedPermissions(): void
    {
        $this->deleteRemovedPermissions();

        $sortOrder = 0;

        foreach (self::$PERMISSIONS as $module => $resources) {
            // Tầng 1: nhóm module (Hệ thống, Quản lý công việc, Phòng họp, Lịch công tác)
            $moduleGroup = Permission::updateOrCreate(
                ['name' => "module:{$module}", 'guard_name' => self::GUARD],
                [
                    'description' => self::$MODULE_LABELS[$module] ?? $module,
                    'sort_order' => $sortOrder++,
                    'parent_id' => null,
                ]
            );

            foreach ($resources as $resource => $actions) {
                // Tầng 2: nhóm resource (group:users, group:roles, ...)
                $groupLabel = self::$RESOURCE_LABELS[$resource] ?? ucfirst($resource);
                $group = Permission::updateOrCreate(
                    ['name' => "group:{$resource}", 'guard_name' => self::GUARD],
                    ['description' => $groupLabel, 'sort_order' => $sortOrder++, 'parent_id' => $moduleGroup->id]
                );

                // Tầng 3: action (users.stats, users.index, ...)
                foreach ($actions as $idx => $action) {
                    Permission::updateOrCreate(
                        ['name' => "{$resource}.{$action}", 'guard_name' => self::GUARD],
                        [
                            'description' => $groupLabel.' - '.(self::$ACTION_LABELS[$action] ?? $action),
                            'sort_order' => $idx,
                            'parent_id' => $group->id,
                        ]
                    );
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Xóa permission đã gộp/bỏ — FK cascade tự gỡ khỏi mọi role đang giữ chúng. */
    protected function deleteRemovedPermissions(): void
    {
        $names = [];
        foreach (self::$REMOVED_PERMISSIONS as $resource => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        Permission::whereIn('name', $names)
            ->orWhereIn('name', self::$REMOVED_GROUPS)
            ->orWhere('name', 'like', 'task-assignment-item-reports.%')
            ->orWhere('name', 'like', 'task-assignment-item-transfers.%')
            ->orWhere('name', 'like', 'task-assignment-item-notes.%')
            ->delete();
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
        Role::firstOrCreate(
            ['name' => 'Tổng hợp lịch', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Thư ký', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Văn phòng', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Lái xe', 'guard_name' => self::GUARD],
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

        $tongHopRole = Role::where('name', 'Tổng hợp lịch')->where('guard_name', self::GUARD)->first();
        if ($tongHopRole) {
            $tongHopRole->syncPermissions($this->getTongHopPermissionNames());
        }

        $thuKyRole = Role::where('name', 'Thư ký')->where('guard_name', self::GUARD)->first();
        if ($thuKyRole) {
            $thuKyRole->syncPermissions($this->getThuKyPermissionNames());
        }

        $vanPhongRole = Role::where('name', 'Văn phòng')->where('guard_name', self::GUARD)->first();
        if ($vanPhongRole) {
            $vanPhongRole->syncPermissions($this->getVanPhongPermissionNames());
        }

        $laiXeRole = Role::where('name', 'Lái xe')->where('guard_name', self::GUARD)->first();
        if ($laiXeRole) {
            $laiXeRole->syncPermissions($this->getLaiXePermissionNames());
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

        $superAdminUser = User::where('user_name', 'admin')
            ->orWhere('email', 'admin@example.com')
            ->first();

        if (! $superAdminUser) {
            $superAdminUser = User::create([
                'email' => 'admin@example.com',
                'name' => 'Admin',
                'user_name' => 'admin',
                'password' => 'quandcore**11',
                'status' => StatusEnum::Active->value,
                'email_verified_at' => now(),
            ]);
        }
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
            // Tìm user đã tồn tại theo user_name HOẶC email — tránh duplicate khi prod đã có account.
            $user = User::where('user_name', $userData['user_name'])
                ->orWhere('email', $userData['email'])
                ->first();

            if (! $user) {
                $user = User::create([
                    'email' => $userData['email'],
                    'user_name' => $userData['user_name'],
                    'name' => $userData['name'],
                    'password' => 'quandcore**11',
                    'status' => StatusEnum::Active->value,
                    'email_verified_at' => now(),
                ]);
            }
            $user->forceFill([
                'created_by' => $superAdminUser->id,
                'updated_by' => $superAdminUser->id,
            ])->save();

            if ($userData['role']) {
                $user->syncRoles([$userData['role']]);
            }
        }

        // Scheduling test users
        $tongHopRole = Role::where('name', 'Tổng hợp lịch')->where('guard_name', self::GUARD)->first();
        $thuKyRole = Role::where('name', 'Thư ký')->where('guard_name', self::GUARD)->first();
        $vanPhongRole = Role::where('name', 'Văn phòng')->where('guard_name', self::GUARD)->first();
        $laiXeRole = Role::where('name', 'Lái xe')->where('guard_name', self::GUARD)->first();

        foreach ([
            ['email' => 'tonghoplich@example.com', 'user_name' => 'tonghoplich', 'name' => 'Tổng hợp lịch', 'role' => $tongHopRole],
            ['email' => 'thuky@example.com', 'user_name' => 'thuky', 'name' => 'Thư ký', 'role' => $thuKyRole],
            ['email' => 'vanphong@example.com', 'user_name' => 'vanphong', 'name' => 'Văn phòng', 'role' => $vanPhongRole],
            ['email' => 'laixe@example.com', 'user_name' => 'laixe', 'name' => 'Lái xe', 'role' => $laiXeRole],
        ] as $userData) {
            $user = User::where('user_name', $userData['user_name'])
                ->orWhere('email', $userData['email'])
                ->first();

            if (! $user) {
                $user = User::create([
                    'email' => $userData['email'],
                    'user_name' => $userData['user_name'],
                    'name' => $userData['name'],
                    'password' => 'quandcore**11',
                    'status' => StatusEnum::Active->value,
                    'email_verified_at' => now(),
                ]);
            }
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
        $flat = self::getFlatPermissions();
        foreach ($flat as $resource => $actions) {
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
        $flat = self::getFlatPermissions();

        // Full quyền trên danh mục
        foreach (['task-assignment-departments', 'task-assignment-employees', 'task-assignment-types', 'task-assignment-item-types'] as $resource) {
            foreach ($flat[$resource] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        // Xem + export văn bản giao việc
        foreach (['index', 'export'] as $action) {
            $names[] = "task-assignment-documents.{$action}";
        }

        // Trang Tổng quan công việc (gộp toàn bộ thống kê dashboard)
        foreach ($flat['task-overview'] as $action) {
            $names[] = "task-overview.{$action}";
        }

        // Đơn thư — full quyền
        foreach ($flat['task-assignment-petitions'] as $action) {
            $names[] = "task-assignment-petitions.{$action}";
        }

        // Dashboard + 2 màn công việc cá nhân (kèm đầy đủ quyền thao tác)
        $names[] = 'dashboard.systemOverview';
        foreach ($flat['my-assigned-tasks'] as $action) {
            $names[] = "my-assigned-tasks.{$action}";
        }
        foreach ($flat['my-received-tasks'] as $action) {
            $names[] = "my-received-tasks.{$action}";
        }

        return $names;
    }

    /** Permission cho Trưởng phòng: tạo/sửa văn bản, công việc, assign, xem báo cáo. */
    protected function getTruongPhongPermissionNames(): array
    {
        return [
            // Văn bản giao việc (kèm quyền thêm/sửa công việc bên trong văn bản)
            'task-assignment-documents.index',
            'task-assignment-documents.store',
            'task-assignment-documents.update',
            'task-assignment-documents.storeItem',
            'task-assignment-documents.updateItem',

            // Tổng quan công việc - BE ép department_id phòng mình
            'task-overview.index',
            'task-overview.exportMonthlyReport',

            // Công việc đang giao (full quyền người giao việc)
            'my-assigned-tasks.index',
            'my-assigned-tasks.export',
            'my-assigned-tasks.pause',
            'my-assigned-tasks.cancel',
            'my-assigned-tasks.transfer',
            'my-assigned-tasks.markDone',
            'my-assigned-tasks.changeStatus',
            'my-assigned-tasks.note',

            // Công việc được giao
            'my-received-tasks.index',
            'my-received-tasks.export',
            'my-received-tasks.updateProgress',
            'my-received-tasks.report',
            'my-received-tasks.note',
            'my-received-tasks.transfer',

            // Dashboard
            'dashboard.systemOverview',
        ];
    }

    /** Permission cho Nhân viên: xem văn bản/công việc, cập nhật tiến độ, tạo/sửa báo cáo. */
    protected function getNhanVienPermissionNames(): array
    {
        return [
            // Công việc được giao (full quyền người thực hiện)
            'my-received-tasks.index',
            'my-received-tasks.updateProgress',
            'my-received-tasks.report',
            'my-received-tasks.note',
            'my-received-tasks.transfer',
        ];
    }

    protected function getTongHopPermissionNames(): array
    {
        $names = [];
        $flat = self::getFlatPermissions();
        foreach (['schedules-executive', 'schedules-office', 'scheduling-employees', 'scheduling-employee-groups', 'scheduling-settings'] as $resource) {
            foreach ($flat[$resource] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }
        return $names;
    }

    protected function getThuKyPermissionNames(): array
    {
        return [
            // Lịch Thường trực — full quyền
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

    protected function getVanPhongPermissionNames(): array
    {
        return [
            'schedules-office.index',
            'schedules-office.show',
            'schedules-office.update',
            'schedules-office.export',
            'schedules-office.stats',
            'schedules-office.approve',
            'schedules-office.home',
        ];
    }

    protected function getLaiXePermissionNames(): array
    {
        return [
            'schedules-executive.driver-view',
            'schedules-executive.home',
            'schedules-office.driver-view',
            'schedules-office.home',
        ];
    }
}
