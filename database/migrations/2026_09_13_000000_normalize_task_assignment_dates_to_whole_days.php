<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chuẩn hoá giờ của dữ liệu đã có về đúng quy ước "chỉ có ngày" (chốt 13/09/2026):
 * `start_at` → 00:00:00; `end_at`, `current_end_at`, `requested_end_at` → 23:59:59.
 *
 * Từ 13/09 model tự chuẩn hoá mọi lần ghi mới; migration này chỉ dọn dữ liệu CŨ.
 * Đo trên máy dev trước khi viết: 161 công việc chuyển từ hệ thống cũ đã đúng
 * (00:00:00 / 23:59:59), 6 công việc của seeder demo đang là 08:00:00.
 *
 * Dùng query builder có chủ đích: chạy trên cả bản ghi đã xoá mềm và không kích
 * hoạt observer. Lịch nhắc đang chờ KHÔNG được dựng lại ở đây — nếu đã bật nhắc
 * hạn trước khi chạy migration, cần dựng lại lịch nhắc cho các công việc bị đổi giờ.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('task_assignment_items')
            ->whereNotNull('start_at')
            ->whereRaw("TIME(start_at) <> '00:00:00'")
            ->update(['start_at' => DB::raw('TIMESTAMP(DATE(start_at))')]);

        DB::table('task_assignment_items')
            ->whereNotNull('end_at')
            ->whereRaw("TIME(end_at) <> '23:59:59'")
            ->update(['end_at' => DB::raw("TIMESTAMP(DATE(end_at), '23:59:59')")]);

        foreach (['current_end_at', 'requested_end_at'] as $column) {
            DB::table('task_assignment_item_extensions')
                ->whereNotNull($column)
                ->whereRaw("TIME({$column}) <> '23:59:59'")
                ->update([$column => DB::raw("TIMESTAMP(DATE({$column}), '23:59:59')")]);
        }
    }

    /** Không đảo ngược được: giờ cũ không được lưu lại ở đâu. */
    public function down(): void {}
};
