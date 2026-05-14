<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm `answer_content` cho đăng ký chất vấn — operator/chair nhập nội dung trả lời
 * khi `type='question'`. `type='discussion'` không dùng field này, vẫn dùng
 * `operator_note` (ghi chú thảo luận).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_discussion_registrations', 'answer_content')) {
                $table->text('answer_content')->nullable()->after('operator_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_discussion_registrations', 'answer_content')) {
                $table->dropColumn('answer_content');
            }
        });
    }
};
