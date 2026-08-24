<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vá quyền module Quản lý công việc sau khi gộp/bỏ permission và sắp lại cây theo nav.
 *
 * Chạy được nhiều lần: snapshot quyền cũ của từng vai trò TRƯỚC khi seeder xóa,
 * sau đó cấp lại quyền mới tương ứng để không vai trò nào bị mất chức năng.
 */
class MigrateTaskPermissionTreeCommand extends Command
{
    protected $signature = 'permissions:migrate-task-tree {--dry-run : Chỉ in ra kế hoạch vá, không ghi DB}';

    protected $description = 'Gộp permission dư thừa của module Quản lý công việc và vá lại quyền cho các vai trò đang có trên server';

    /** Guard thống nhất với PermissionSeeder. */
    protected const GUARD = 'web';

    /** Quyền cũ (sẽ bị xóa) => quyền mới thay thế. */
    protected const MAP = [
        // Thống kê dashboard gộp về trang Tổng quan công việc
        'task-assignment-items.stats' => ['task-overview.index'],
        'task-assignment-items.statsByDepartment' => ['task-overview.index'],
        'task-assignment-items.statsByUser' => ['task-overview.index'],
        'task-assignment-items.statsByTime' => ['task-overview.index'],
        'task-assignment-items.overdue' => ['task-overview.index'],
        'task-assignment-items.upcomingDeadline' => ['task-overview.index'],
        'task-assignment-items.statsByItemType' => ['task-overview.index'],
        'task-assignment-items.statsByDocument' => ['task-overview.index'],
        'task-assignment-items.exportMonthlyReport' => ['task-overview.exportMonthlyReport'],
        'task-assignment-documents.stats' => ['task-overview.index'],
        'task-assignment-documents.statsByTime' => ['task-overview.index'],

        // Thao tác công việc đã tách sang 2 màn hình cá nhân
        'task-assignment-items.pause' => ['my-assigned-tasks.pause'],
        'task-assignment-items.cancel' => ['my-assigned-tasks.cancel'],
        'task-assignment-items.markDone' => ['my-assigned-tasks.markDone'],
        'task-assignment-items.changeStatus' => ['my-assigned-tasks.changeStatus'],
        'task-assignment-items.updateProgress' => ['my-received-tasks.updateProgress'],

        // CRUD công việc chỉ diễn ra trong màn Văn bản giao việc → dùng chung quyền văn bản
        'task-assignment-items.store' => ['task-assignment-documents.storeItem'],
        'task-assignment-items.update' => ['task-assignment-documents.updateItem'],
        'task-assignment-items.destroy' => ['task-assignment-documents.destroyItem'],

        // Xuất Excel công việc có ở cả 2 màn cá nhân
        'task-assignment-items.export' => ['my-assigned-tasks.export', 'my-received-tasks.export'],

        // .show gộp vào .index
        'task-assignment-documents.show' => ['task-assignment-documents.index'],
        'task-assignment-departments.show' => ['task-assignment-departments.index'],
        'task-assignment-employees.show' => ['task-assignment-employees.index'],
        'my-assigned-tasks.show' => ['my-assigned-tasks.index'],
        'my-received-tasks.show' => ['my-received-tasks.index'],

        // .stats gộp vào .index
        'task-assignment-departments.stats' => ['task-assignment-departments.index'],
        'task-assignment-petitions.stats' => ['task-assignment-petitions.index'],

        // .bulkDestroy gộp vào .destroy
        'task-assignment-items.bulkDestroy' => ['task-assignment-documents.destroyItem'],
        'task-assignment-departments.bulkDestroy' => ['task-assignment-departments.destroy'],

        // .bulkUpdateStatus / .changeStatus gộp vào .update
        'task-assignment-items.bulkUpdateStatus' => ['task-assignment-documents.updateItem'],
        'task-assignment-documents.changeStatus' => ['task-assignment-documents.update'],
        'task-assignment-departments.bulkUpdateStatus' => ['task-assignment-departments.update'],
        'task-assignment-departments.changeStatus' => ['task-assignment-departments.update'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('1. Đọc quyền hiện có của các vai trò (trước khi seeder xóa quyền cũ)...');
        $plan = $this->buildPlan();

        if ($plan === []) {
            $this->line('-> Không vai trò nào giữ quyền cũ, chỉ cần chạy lại seeder.');
        }

        foreach ($plan as $orgId => $roles) {
            foreach ($roles as $roleName => $names) {
                $this->line("   [org: {$orgId}] {$roleName} → ".implode(', ', $names));
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run: không ghi DB.');

            return self::SUCCESS;
        }

        $this->info('2. Chạy PermissionSeeder (dựng lại cây theo nav + xóa quyền đã gộp)...');
        (new PermissionSeeder)->run();

        $this->info('3. Cấp lại quyền mới cho từng vai trò theo bảng ánh xạ...');
        $patched = $this->applyPlan($plan);
        $this->line("-> Đã vá {$patched} lượt vai trò.");

        $this->info('4. Xóa cache permission...');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('=== HOÀN THÀNH VÁ CÂY PERMISSION QUẢN LÝ CÔNG VIỆC ===');

        return self::SUCCESS;
    }

    /**
     * Duyệt mọi tổ chức, ghi lại vai trò nào cần được cấp quyền mới nào.
     *
     * @return array<int|string, array<string, array<int, string>>> [organization_id => [role_name => new permissions]]
     */
    protected function buildPlan(): array
    {
        $plan = [];

        foreach ($this->organizationIds() as $orgId) {
            if ($orgId !== null) {
                setPermissionsTeamId($orgId);
            }

            foreach (Role::with('permissions:id,name')->get() as $role) {
                $granted = [];
                foreach ($role->permissions->pluck('name') as $name) {
                    foreach (self::MAP[$name] ?? [] as $replacement) {
                        $granted[$replacement] = true;
                    }
                }

                if ($granted !== []) {
                    $plan[$orgId ?? 'global'][$role->name] = array_keys($granted);
                }
            }
        }

        return $plan;
    }

    /** Cấp quyền mới theo kế hoạch; bỏ qua tên quyền không tồn tại để không ném PermissionDoesNotExist. */
    protected function applyPlan(array $plan): int
    {
        $patched = 0;

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
                    $patched++;
                }
            }
        }

        return $patched;
    }

    /** Vai trò global (null) + từng tổ chức. */
    protected function organizationIds(): array
    {
        return array_merge([null], Organization::pluck('id')->all());
    }
}
