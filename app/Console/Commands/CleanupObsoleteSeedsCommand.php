<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

/**
 * Cleanup permission/setting đã bị gỡ khỏi seeder nhưng còn record trong DB.
 *
 * Seeder dùng firstOrCreate/updateOrCreate nên chỉ tạo mới, không xóa record cũ.
 * Khi refactor (rename/remove permission hoặc setting key), cần chạy command này
 * để đồng bộ DB với seeder.
 *
 * Usage:
 *   php artisan seed:cleanup-obsolete           # xóa thật
 *   php artisan seed:cleanup-obsolete --dry-run # chỉ hiển thị, không xóa
 */
class CleanupObsoleteSeedsCommand extends Command
{
    protected $signature = 'seed:cleanup-obsolete {--dry-run : Chỉ liệt kê, không xóa}';

    protected $description = 'Xóa permissions/settings orphan (có trong DB nhưng không còn trong seeder).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? '[DRY-RUN] Không có gì bị xóa.' : 'Xóa record orphan...');
        $this->newLine();

        $permissionsRemoved = $this->cleanupPermissions($dryRun);
        $settingsRemoved = $this->cleanupSettings($dryRun);

        $this->newLine();
        $this->info("Permissions: {$permissionsRemoved} orphan.");
        $this->info("Settings: {$settingsRemoved} orphan.");

        if (! $dryRun && $permissionsRemoved > 0) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            Setting::clearCache();
            $this->info('Cache cleared.');
        }

        return self::SUCCESS;
    }

    private function cleanupPermissions(bool $dryRun): int
    {
        $valid = $this->collectSeederPermissionNames();
        $orphans = Permission::whereNotIn('name', $valid)->get(['id', 'name']);

        if ($orphans->isEmpty()) {
            $this->line('  Permissions: OK (không có orphan).');

            return 0;
        }

        $this->warn("  Orphan permissions ({$orphans->count()}):");
        foreach ($orphans as $p) {
            $this->line("    - [{$p->id}] {$p->name}");
        }

        if ($dryRun) {
            return $orphans->count();
        }

        $ids = $orphans->pluck('id')->all();
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        Permission::whereIn('id', $ids)->delete();

        return $orphans->count();
    }

    private function cleanupSettings(bool $dryRun): int
    {
        $valid = $this->collectSeederSettingKeys();
        $orphans = Setting::whereNotIn('key', $valid)->get(['id', 'key', 'group']);

        if ($orphans->isEmpty()) {
            $this->line('  Settings: OK (không có orphan).');

            return 0;
        }

        $this->warn("  Orphan settings ({$orphans->count()}):");
        foreach ($orphans as $s) {
            $this->line("    - [{$s->id}] {$s->key} (group: {$s->group})");
        }

        if ($dryRun) {
            return $orphans->count();
        }

        Setting::whereIn('id', $orphans->pluck('id')->all())->delete();

        return $orphans->count();
    }

    /**
     * Gom danh sách tên permission hợp lệ từ PermissionSeeder::$PERMISSIONS.
     * Bao gồm cả permission con (resource.action) và group cha (group:resource).
     */
    private function collectSeederPermissionNames(): array
    {
        $permissions = $this->getSeederStaticProperty(PermissionSeeder::class, 'PERMISSIONS');

        $names = [];
        foreach ($permissions as $resource => $actions) {
            $names[] = "group:{$resource}";
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        return $names;
    }

    /**
     * Gom danh sách key setting hợp lệ từ SettingSeeder::$items.
     */
    private function collectSeederSettingKeys(): array
    {
        $items = $this->getSeederStaticProperty(SettingSeeder::class, 'items');

        return array_column($items, 'key');
    }

    private function getSeederStaticProperty(string $class, string $property): array
    {
        $ref = new ReflectionClass($class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue();
    }
}
