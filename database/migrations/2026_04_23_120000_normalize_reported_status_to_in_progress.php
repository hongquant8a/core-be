<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bỏ status `reported` khỏi enum TaskProgressStatusEnum.
 * Chuyển task hiện đang `reported` về `in_progress` (assignee có thể submit report
 * thay vì set status này — submit report là kênh duy nhất để signal "tôi xong").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('task_assignment_items')
            ->where('processing_status', 'reported')
            ->update(['processing_status' => 'in_progress']);
    }

    public function down(): void
    {
        // No-op: không rollback được vì không lưu lại record nào đã chuyển.
    }
};
