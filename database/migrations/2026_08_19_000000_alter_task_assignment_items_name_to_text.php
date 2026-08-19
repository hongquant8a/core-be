<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tên công việc trước đây là VARCHAR(255). Tên do AI trích xuất từ văn bản
     * hành chính thường là cả một câu chỉ đạo ("Đôn đốc, chỉ đạo các trường học
     * trên địa bàn phường...") nên vượt 255 rất thường xuyên. Đổi sang TEXT để
     * bỏ trần thực tế.
     *
     * Cột `name` của bảng này không nằm trong index nào (xem migration
     * 2026_04_02_000000) nên đổi sang TEXT không phá index.
     */
    public function up(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->text('name')->change();
        });
    }

    public function down(): void
    {
        // Cắt bớt dữ liệu dài trước khi thu cột lại, nếu không MySQL strict mode
        // sẽ ném lỗi và rollback chết giữa chừng.
        DB::table('task_assignment_items')
            ->whereRaw('CHAR_LENGTH(name) > 255')
            ->update(['name' => DB::raw('LEFT(name, 255)')]);

        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
