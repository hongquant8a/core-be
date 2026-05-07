<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Highlight pointers cho Tab 7 Điều hành — operator click chương trình/đăng ký
 * thảo luận sẽ set 2 FK này, FE màn chiếu (Tab 8) polling meeting để biết
 * chương trình + lượt phát biểu nào đang highlight.
 *
 *  - current_meeting_agenda_id: chương trình đang được focus.
 *  - current_meeting_discussion_registration_id: đăng ký phát biểu/chất vấn
 *    đang được focus (thuộc 1 chương trình cụ thể, FE đảm bảo cùng meeting).
 *
 * Cả 2 set null = không highlight gì. SET NULL on delete để tránh ràng buộc cứng
 * khi xoá agenda/registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'current_meeting_agenda_id')) {
                $table->foreignId('current_meeting_agenda_id')
                    ->nullable()
                    ->after('runtime_ended_at')
                    ->constrained('meeting_agendas')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'current_meeting_discussion_registration_id')) {
                $table->foreignId('current_meeting_discussion_registration_id')
                    ->nullable()
                    ->after('current_meeting_agenda_id')
                    ->constrained('meeting_discussion_registrations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            foreach (['current_meeting_discussion_registration_id', 'current_meeting_agenda_id'] as $col) {
                if (Schema::hasColumn('meetings', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
        });
    }
};
