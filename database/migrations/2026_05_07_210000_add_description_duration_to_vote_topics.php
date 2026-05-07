<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung 2 trường cho phiên biểu quyết — operator nhập lúc bấm "Bắt đầu" trong Tab 7:
 *
 *  - description: nội dung diễn giải hiển thị trên popup biểu quyết của đại biểu
 *    + màn chiếu (cách phát biểu rõ ràng vấn đề đang bỏ phiếu).
 *  - duration_minutes: thời lượng phiên (FE đếm ngược, BE chỉ lưu giá trị tham chiếu;
 *    không tự đóng — operator vẫn phải bấm /close).
 *
 * Cả 2 đều nullable để giữ tương thích vote-topic cũ chưa có 2 trường.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_vote_topics')) {
            return;
        }

        Schema::table('meeting_vote_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_vote_topics', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (! Schema::hasColumn('meeting_vote_topics', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_vote_topics')) {
            return;
        }

        Schema::table('meeting_vote_topics', function (Blueprint $table) {
            foreach (['duration_minutes', 'description'] as $col) {
                if (Schema::hasColumn('meeting_vote_topics', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
