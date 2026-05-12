<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm timestamp highlighted_at vào meeting_discussion_registrations.
 * Khi operator highlight 1 registration -> set highlighted_at = now() cho row đó,
 * clear highlighted_at cho row trước (1 highlight/meeting tại 1 thời điểm).
 *
 * FE dùng để compute countdown speaker_duration_minutes (lấy từ agenda) - (now - highlighted_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_discussion_registrations')) {
            return;
        }
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_discussion_registrations', 'highlighted_at')) {
                $table->dateTime('highlighted_at')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_discussion_registrations')) {
            return;
        }
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_discussion_registrations', 'highlighted_at')) {
                $table->dropColumn('highlighted_at');
            }
        });
    }
};
