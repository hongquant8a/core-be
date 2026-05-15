<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung flag `allow_vote_registration` cho chương trình họp — pattern giống
 * `allow_discussion_registration` + `allow_question_registration`.
 *
 * Khi flag = true: chương trình này có biểu quyết. FE Tab Điều hành/Chủ trì hiển
 * thị tab biểu quyết tương ứng. Vote topic gắn vào agenda qua FK
 * `meeting_vote_topics.meeting_agenda_id` (đã có sẵn).
 *
 * Duration của từng phiên biểu quyết lấy từ `meeting_vote_topics.duration_minutes`
 * — KHÔNG cần `vote_duration_minutes` ở agenda level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_agendas', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_agendas', 'allow_vote_registration')) {
                $table->boolean('allow_vote_registration')->default(false)->after('question_duration_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_agendas', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_agendas', 'allow_vote_registration')) {
                $table->dropColumn('allow_vote_registration');
            }
        });
    }
};
