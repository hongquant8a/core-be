<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bỏ giá trị enum 'overdue' khỏi processing_status — chuyển sang 2 computed
 * flag `is_overdue` / `is_late_completed` derive từ so sánh dates.
 *
 * Migrate dữ liệu cũ: task có processing_status='overdue' → 'in_progress'.
 * (Là `is_overdue=true` ở Resource khi end_at < now.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('task_assignment_items')
            ->where('processing_status', 'overdue')
            ->update(['processing_status' => 'in_progress']);
    }

    public function down(): void
    {
        // Không thể restore — irreversible (mất thông tin task nào đã từng overdue).
    }
};
