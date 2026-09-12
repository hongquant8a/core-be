<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xoá mềm cho văn bản giao việc, công việc và báo cáo công việc.
 *
 * Trước đây cả ba đều xoá cứng, trong khi xoá một văn bản kéo theo cascade toàn
 * bộ công việc bên trong, rồi báo cáo, rồi tệp đính kèm — một cú bấm nhầm là mất
 * sạch không lấy lại được. Đơn thư đã có xoá mềm từ 11/09/2026; đây là phần còn
 * lại của module.
 *
 * Lưu ý: khoá ngoại cascade chỉ kích hoạt khi XOÁ CỨNG. Sau migration này, xoá
 * mềm một văn bản KHÔNG tự kéo theo công việc — việc đó do service làm tường
 * minh để còn khôi phục lại đúng bộ.
 */
return new class extends Migration
{
    private const TABLES = [
        'task_assignment_documents',
        'task_assignment_items',
        'task_assignment_item_reports',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
