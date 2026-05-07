<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ column `status` khỏi meeting_vote_topics — phase derive từ opened_at + closed_at.
 *
 *  - opened_at NULL                       → 'draft'
 *  - opened_at NOT NULL + closed_at NULL  → 'opened'
 *  - closed_at NOT NULL                   → 'closed'
 *
 * Filter `?status=opened` BE tự compute. duration_minutes vẫn giữ lại để FE đếm
 * ngược trong popup biểu quyết, nhưng BE không auto-close khi hết giờ — operator
 * phải bấm /close manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_vote_topics')) {
            return;
        }

        Schema::table('meeting_vote_topics', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_vote_topics', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_vote_topics')) {
            return;
        }

        Schema::table('meeting_vote_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_vote_topics', 'status')) {
                $table->string('status')->default('draft')->after('sort_order');
            }
        });
    }
};
