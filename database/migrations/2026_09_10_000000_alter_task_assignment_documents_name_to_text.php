<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tên văn bản giao việc trước đây là VARCHAR(255) — cùng vấn đề đã xử lý cho
     * `task_assignment_items.name` ở migration 2026_08_19_000000.
     *
     * Văn bản hành chính thật thường gói cả số hiệu, ngày ban hành, cơ quan ban
     * hành và trích yếu vào tiêu đề ("Thông báo số 315/TB-VP - 04/08/2026 - UBND
     * Phường Hòa Khánh\nkết luận của đồng chí ... tại buổi kiểm tra ..."), nên
     * vượt 255 là chuyện bình thường: 17/100 văn bản của phường Hoà Khánh dài
     * 257–365 ký tự. Cắt chuỗi ở 255 làm mất phần đuôi trích yếu.
     *
     * Cột `name` không nằm trong index nào (xem migration 2026_04_02_000000) nên
     * đổi sang TEXT không phá index.
     */
    public function up(): void
    {
        Schema::table('task_assignment_documents', function (Blueprint $table) {
            $table->text('name')->change();
        });
    }

    public function down(): void
    {
        // Cắt bớt dữ liệu dài trước khi thu cột lại, nếu không MySQL strict mode
        // sẽ ném lỗi và rollback chết giữa chừng.
        DB::table('task_assignment_documents')
            ->whereRaw('CHAR_LENGTH(name) > 255')
            ->update(['name' => DB::raw('LEFT(name, 255)')]);

        Schema::table('task_assignment_documents', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
