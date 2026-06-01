<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class SyncDeletedMigrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:sync-deleted {--dry-run : Only list deleted migrations and tables to drop, without making changes} {--force : Force the operation to run without prompting for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động phát hiện các file migration đã bị xóa khỏi codebase, drop các bảng tương ứng và xóa log trong bảng migrations.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->info('[DRY-RUN] Đang chạy ở chế độ giả lập. Không có thay đổi nào được thực thi.');
        } else {
            $this->info('Đang quét và đồng bộ các migration đã bị xóa...');
        }
        $this->newLine();

        // 1. Kiểm tra bảng migrations có tồn tại
        if (!Schema::hasTable('migrations')) {
            $this->error('Bảng migrations không tồn tại trong database.');
            return self::FAILURE;
        }

        $dbMigrations = DB::table('migrations')->pluck('migration')->all();

        // 2. Lấy danh sách các file migration hiện tại ở database/migrations/
        $migrationFiles = File::files(database_path('migrations'));
        $existingMigrations = [];
        foreach ($migrationFiles as $file) {
            $existingMigrations[] = $file->getBasename('.php');
        }

        // 3. Tìm các migration có trong DB nhưng không còn file trong codebase
        $rawDeletedMigrations = array_diff($dbMigrations, $existingMigrations);

        // Lọc chỉ giữ lại các migration liên quan đến module Scheduling
        $deletedMigrations = array_filter($rawDeletedMigrations, function ($migration) {
            return (bool) preg_match('/(schedule|scheduling|notification_group|reminder_preset|filter_preset)/i', $migration);
        });

        if (empty($deletedMigrations)) {
            $this->info('Không phát hiện file migration nào của module Scheduling bị xóa khỏi codebase!');
            return self::SUCCESS;
        }

        $this->warn(sprintf('Phát hiện %d migration(s) trong DB đã bị xóa file khỏi codebase:', count($deletedMigrations)));
        foreach ($deletedMigrations as $migration) {
            $this->line("  - {$migration}");
        }
        $this->newLine();

        // 4. Nhận diện các bảng cần drop tương ứng
        $tablesToDrop = [];
        $hasDataWarning = false;
        
        // Chỉ cho phép drop các bảng thuộc module Scheduling
        $allowedTables = [
            'schedules', 
            'schedule_attachments', 
            'schedule_participants', 
            'schedule_reminders', 
            'scheduling_employees', 
            'scheduling_employee_groups', 
            'scheduling_employee_group_members', 
            'scheduling_settings', 
            'scheduling_filter_presets', 
            'notification_groups', 
            'notification_group_members', 
            'reminder_presets', 
            'org_scheduling_settings', 
            'filter_presets'
        ];

        foreach ($deletedMigrations as $migration) {
            // Khớp các migration dạng: create_table_name_table hoặc create_table_name_tables
            if (preg_match('/_create_(.+)_tables?$/i', $migration, $matches)) {
                $tableName = strtolower($matches[1]);
                if (Schema::hasTable($tableName)) {
                    if (!in_array($tableName, $allowedTables)) {
                        $this->warn("⚠️ Bỏ qua bảng '{$tableName}' vì không thuộc whitelist các bảng của module Scheduling.");
                        continue;
                    }
                    $tablesToDrop[$migration] = $tableName;
                }
            }
        }

        if (!empty($tablesToDrop)) {
            $this->warn('Các bảng sau đây liên quan đến migration bị xóa sẽ bị drop khỏi database:');
            foreach ($tablesToDrop as $migration => $table) {
                $rowCount = DB::table($table)->count();
                if ($rowCount > 0) {
                    $this->error("  - Bảng '{$table}' (thuộc migration: {$migration}) [⚠️ CẢNH BÁO: ĐANG CÓ {$rowCount} BẢN GHI!]");
                    $hasDataWarning = true;
                } else {
                    $this->line("  - Bảng '{$table}' (thuộc migration: {$migration}) [Trống - 0 bản ghi]");
                }
            }
            $this->newLine();
        } else {
            $this->info('Không có bảng nào cần drop (hoặc bảng không tồn tại trong DB).');
            $this->newLine();
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Hoàn tất. Không có thay đổi nào được ghi vào database.');
            return self::SUCCESS;
        }

        // Xác nhận từ user
        $confirmMessage = $hasDataWarning 
            ? '⚠️ CẢNH BÁO cực kỳ quan trọng: Có bảng chứa dữ liệu thật! Bạn có chắc chắn muốn drop các bảng trên và xóa log migration không?'
            : 'Bạn có chắc chắn muốn drop các bảng trên và xóa bản ghi migration khỏi DB không?';

        if (!$force && !$this->confirm($confirmMessage, false)) {
            $this->warn('Hủy bỏ hành động.');
            return self::SUCCESS;
        }

        if ($hasDataWarning && !$force) {
            $confirmText = 'DONG Y XOA';
            $input = $this->ask("Để xác nhận drop bảng ĐANG CÓ DỮ LIỆU, vui lòng gõ chính xác cụm từ '{$confirmText}':");
            if ($input !== $confirmText) {
                $this->error('Xác nhận không khớp. Đã hủy bỏ thao tác để bảo vệ an toàn dữ liệu.');
                return self::SUCCESS;
            }
        }

        // 5. Thực thi drop bảng và xóa migration log
        Schema::disableForeignKeyConstraints();

        foreach ($tablesToDrop as $migration => $table) {
            $this->comment("Đang drop bảng '{$table}'...");
            Schema::dropIfExists($table);
        }

        $this->comment('Đang xóa log migration khỏi DB...');
        DB::table('migrations')->whereIn('migration', array_values($deletedMigrations))->delete();

        Schema::enableForeignKeyConstraints();

        $this->info('Đồng bộ thành công! Bây giờ bạn đã có thể chạy an toàn: php artisan migrate');
        return self::SUCCESS;
    }
}
