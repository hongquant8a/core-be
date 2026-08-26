<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
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
     * Danh sách đầy đủ permission theo nhóm module (Core, TaskAssignment, Meeting).
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
            // Xem/xoá lịch sử chat nhóm nội bộ theo cuộc họp (meeting.internal_chat_enabled).
            'meeting-chat-conversations' => [
                'index', 'show', 'destroy',
            ],
        ],
        // TaskAssignment — thứ tự resource theo đúng sidebar core-fe.
        'TaskAssignment' => [
            'task-overview' => [
                'index', 'exportMonthlyReport',
                // Ba quyền "vượt phạm vi" — thay cho việc hardcode tên vai trò trong code.
                // Phạm vi xem công việc có ba bậc, xét theo đúng thứ tự này:
                //  viewAll        : xem dữ liệu toàn tổ chức.
                //  viewDepartment : xem mọi công việc của các phòng ban mình là thành viên
                //                   (dành cho trưởng phòng / người theo dõi cấp phòng).
                //  không có gì    : chỉ thấy công việc mình giao hoặc được giao.
                // manageAll là trục khác — thao tác trên công việc của người khác
                // (bỏ kiểm tra sở hữu), không phải phạm vi xem.
                'viewAll', 'viewDepartment', 'manageAll',
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
                // Xem MỌI công việc được giao cho thành viên phòng ban mình (không chỉ
                // của bản thân). Cấp cho vai trò theo dõi cấp phòng (vd Trưởng phòng).
                'viewDepartment',
            ],
            'presentation' => [
                'index',
            ],
            'task-assignment-petitions' => [
                'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'manage',
                // Xem/thao tác đơn thư của MỌI phòng ban. Không có quyền này thì chỉ
                // thấy đơn thư của phòng ban mình thuộc về.
                'viewAll',
            ],
            'task-assignment-types' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'task-assignment-item-types' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'task-assignment-departments' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
            'task-assignment-employees' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
            ],
        ],
        'Meeting' => [
            'meetings' => [
                'stats', 'index', 'show', 'store', 'update', 'destroy',
                'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export',
                'exportReports', 'home',
                // Xem chi tiết điểm danh/biểu quyết theo từng người (góc nhìn quản trị).
                'viewAll',
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
        // Quan hệ nhân viên ↔ phòng ban nay là một trường của hai form (employee_ids /
        // department_ids), không còn endpoint riêng nên 3 quyền này bị bỏ.
        'task-assignment-departments' => ['users', 'syncUsers', 'removeUser'],
        // task-assignment-types và task-assignment-item-types KHÔNG còn gộp: hai danh mục này
        // tách đủ 11 quyền (kể cả changeStatus 1 bản ghi và bulkUpdateStatus hàng loạt)
        // theo yêu cầu nghiệp vụ.
        'task-assignment-petitions' => ['stats'],
        'my-assigned-tasks' => ['show'],
        'my-received-tasks' => ['show'],
    ];

    /**
     * Vai trò đã bỏ — xóa khỏi DB mỗi lần seed.
     * `Quản trị` gộp vào `Quản lý công việc`; 3 vai trò Lịch công tác và `Đại biểu`
     * không còn tài khoản nào dùng và không chỗ nào trong code kiểm tra tới.
     */
    protected static array $REMOVED_ROLES = [
        'Quản trị',
        'Tổng hợp lịch',
        'Thư ký',
        'Văn phòng',
        'Đại biểu',
        'Lái xe',
        'Admin',
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
        $this->deleteRemovedRoles();
        $this->assignPermissionsToRoles();
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
                'name' => 'Danatec',
                'description' => 'Tổ chức mặc định của hệ thống',
                'status' => StatusEnum::Active->value,
            ]
        );
    }

    /** Nhãn tiếng Việt cho module. */
    protected static array $MODULE_LABELS = [
        'Core' => 'Hệ thống',
        'TaskAssignment' => 'Quản lý công việc',
        'Meeting' => 'Phòng họp không giấy',
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
        'meeting-chat-conversations' => 'Chat nhóm cuộc họp',
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
        'confirm' => 'Xác nhận',
        'test' => 'Kiểm thử',
        'complete' => 'Đánh dấu hoàn thành',
        'approve' => 'Duyệt',
        'reject' => 'Từ chối',
        'attendees' => 'Quản lý đại biểu trong nhóm',
        'systemOverview' => 'Tổng quan hệ thống',
        'viewAll' => 'Xem toàn tổ chức',
        'viewDepartment' => 'Xem dữ liệu phòng ban mình',
        'manageAll' => 'Thao tác trên dữ liệu của người khác',
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
        // TaskAssignment - 3 role nghiệp vụ giao việc
        Role::firstOrCreate(
            ['name' => 'Quản lý công việc', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Nhân viên', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );

        // Chuẩn hóa dữ liệu cũ nếu còn role theo organization.
        Role::query()->update(['organization_id' => null]);
    }

    /** Xóa vai trò đã bỏ (kèm quan hệ gán quyền và gán user do khóa ngoại cascade). */
    protected function deleteRemovedRoles(): void
    {
        $roles = Role::whereIn('name', self::$REMOVED_ROLES)
            ->where('guard_name', self::GUARD)
            ->get();

        foreach ($roles as $role) {
            $role->syncPermissions([]);
            $role->delete();
        }
    }

    /** Gán permission cho từng role. */
    protected function assignPermissionsToRoles(): void
    {
        $allPermissionNames = $this->getAllPermissionNames();
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', self::GUARD)->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions($allPermissionNames);
        }

        // TaskAssignment roles
        $quanLyCongViecRole = Role::where('name', 'Quản lý công việc')->where('guard_name', self::GUARD)->first();
        if ($quanLyCongViecRole) {
            $quanLyCongViecRole->syncPermissions($this->getQuanLyCongViecPermissionNames());
        }

        $nhanVienRole = Role::where('name', 'Nhân viên')->where('guard_name', self::GUARD)->first();
        if ($nhanVienRole) {
            $nhanVienRole->syncPermissions($this->getNhanVienPermissionNames());
        }
    }

    /**
     * Permission cho Nhân viên — người thực hiện: nhận việc, cập nhật tiến độ,
     * làm báo cáo. KHÔNG có markDone và KHÔNG có transfer; xác nhận hoàn thành và
     * điều chuyển đều thuộc Quản lý công việc.
     */
    protected function getNhanVienPermissionNames(): array
    {
        return [
            'my-received-tasks.index',
            'my-received-tasks.export',
            'my-received-tasks.updateProgress',
            'my-received-tasks.report',
            'my-received-tasks.note',
            // KHÔNG có `my-received-tasks.transfer`: điều chuyển công việc là thao
            // tác của người giao việc. Đã bỏ khỏi vai trò này ngày 25/08/2026 —
            // đừng thêm lại nếu không có yêu cầu nghiệp vụ mới.

            // Đơn thư: thêm, xem và xử lý đơn của phòng ban mình.
            // Phạm vi dữ liệu do policy quyết định: không có
            // `task-assignment-petitions.viewAll` thì chỉ thấy đơn của phòng ban mình.
            // Xóa / xóa hàng loạt / mở khóa cần `.destroy` / `.bulkDestroy` / `.manage`
            // — vai trò này không có cả ba.
            'task-assignment-petitions.index',
            'task-assignment-petitions.show',
            'task-assignment-petitions.store',
            'task-assignment-petitions.update',
            'task-assignment-petitions.changeStatus',
        ];
    }

    /** Toàn bộ tên permission dạng phẳng — dùng cho Super Admin. */
    protected function getAllPermissionNames(): array
    {
        $names = [];
        foreach (self::getFlatPermissions() as $resource => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        return $names;
    }

    /**
     * Permission cho Quản lý công việc — người giao và xác nhận:
     * full 4 danh mục, full văn bản giao việc (kèm CRUD công việc bên trong),
     * tổng quan, trình diễn và toàn bộ thao tác trên 2 màn công việc cá nhân.
     */
    protected function getQuanLyCongViecPermissionNames(): array
    {
        $names = [];
        $flat = self::getFlatPermissions();

        foreach ([
            'task-assignment-types',
            'task-assignment-item-types',
            'task-assignment-departments',
            'task-assignment-employees',
        ] as $resource) {
            foreach ($flat[$resource] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        foreach (['task-assignment-documents', 'task-overview', 'my-assigned-tasks', 'my-received-tasks'] as $resource) {
            foreach ($flat[$resource] as $action) {
                // Hai quyền của task-overview KHÔNG seed cho vai trò nghiệp vụ:
                //
                //  manageAll          : bỏ kiểm tra sở hữu — chỉ dành cho quản trị hệ thống.
                //  exportMonthlyReport: báo cáo giao ban gộp số liệu mọi phòng ban, là
                //                       việc của cấp điều hành. Quản trị tự cấp ở màn Vai
                //                       trò cho đúng người khi cần, không mặc định.
                //
                // Ai được cấp mà không có viewAll/viewDepartment sẽ nhận 403 — xem
                // TaskAssignmentItemService::exportMonthlyReport().
                if ($resource === 'task-overview' && in_array($action, ['manageAll', 'exportMonthlyReport'], true)) {
                    continue;
                }

                $names[] = "{$resource}.{$action}";
            }
        }

        // Đơn thư — toàn quyền, kể cả `viewAll` nên không bị giới hạn theo phòng ban.
        foreach ($flat['task-assignment-petitions'] as $action) {
            $names[] = "task-assignment-petitions.{$action}";
        }

        $names[] = 'presentation.index';
        // KHÔNG có `dashboard.systemOverview`: tổng quan hệ thống là màn quản trị,
        // không thuộc phân hệ công việc. Đã bỏ khỏi vai trò này ngày 25/08/2026.

        return $names;
    }
}
