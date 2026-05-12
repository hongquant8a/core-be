<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm thời lượng đăng ký (phút) cho từng mục chương trình:
 *  - discussion_duration_minutes: thời lượng cho phép đăng ký thảo luận (chỉ áp dụng khi allow_discussion_registration=true)
 *  - question_duration_minutes:   thời lượng cho phép đăng ký chất vấn (chỉ áp dụng khi allow_question_registration=true)
 *
 * Null = chưa cấu hình (FE có thể default 30 phút). Đơn vị: phút.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_agendas')) {
            return;
        }
        Schema::table('meeting_agendas', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_agendas', 'discussion_duration_minutes')) {
                $table->unsignedSmallInteger('discussion_duration_minutes')->nullable()->after('allow_discussion_registration');
            }
            if (! Schema::hasColumn('meeting_agendas', 'question_duration_minutes')) {
                $table->unsignedSmallInteger('question_duration_minutes')->nullable()->after('allow_question_registration');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_agendas')) {
            return;
        }
        Schema::table('meeting_agendas', function (Blueprint $table) {
            foreach (['discussion_duration_minutes', 'question_duration_minutes'] as $col) {
                if (Schema::hasColumn('meeting_agendas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
