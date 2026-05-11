<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng meeting_vote_responses cho chủ trì vote được (không cần participant row).
 *  - Thêm user_id (NOT NULL sau backfill từ participant.attendee.user_id).
 *  - meeting_participant_id -> nullable (chủ trì không có participant row).
 *  - Đổi unique (topic_id, participant_id) -> (topic_id, user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_vote_responses')) {
            return;
        }

        if (! Schema::hasColumn('meeting_vote_responses', 'user_id')) {
            Schema::table('meeting_vote_responses', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('meeting_vote_topic_id');
                $table->index('user_id', 'mvr_user_idx');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });

            // Backfill user_id từ participant.attendee.user_id.
            DB::statement('
                UPDATE meeting_vote_responses r
                INNER JOIN meeting_participants p ON p.id = r.meeting_participant_id
                INNER JOIN meeting_attendees a ON a.id = p.meeting_attendee_id
                SET r.user_id = a.user_id
                WHERE r.user_id IS NULL
            ');
        }

        // Helper check FK + index tồn tại không (idempotent).
        $fkExists = fn (string $column) => collect(DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'meeting_vote_responses'
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$column]))->isNotEmpty();
        $indexExists = fn (string $name) => collect(DB::select(
            "SHOW INDEX FROM meeting_vote_responses WHERE Key_name = ?",
            [$name]
        ))->isNotEmpty();

        // Drop FK participant trước (nếu còn) để có thể đổi nullable + drop old unique.
        if ($fkExists('meeting_participant_id')) {
            Schema::table('meeting_vote_responses', function (Blueprint $table) {
                $table->dropForeign(['meeting_participant_id']);
            });
        }

        // Tạo new unique TRƯỚC khi drop old — để FK topic_id có sẵn index thay thế.
        // (Old unique là composite (topic_id, participant_id) — index duy nhất với topic_id leftmost.)
        if (! $indexExists('meeting_vote_resp_topic_user_unique')) {
            Schema::table('meeting_vote_responses', function (Blueprint $table) {
                $table->unique(['meeting_vote_topic_id', 'user_id'], 'meeting_vote_resp_topic_user_unique');
            });
        }

        // Bây giờ mới drop được old unique vì FK topic_id đã có index khác.
        if ($indexExists('meeting_vote_resp_topic_part_unique')) {
            Schema::table('meeting_vote_responses', function (Blueprint $table) {
                $table->dropUnique('meeting_vote_resp_topic_part_unique');
            });
        }

        Schema::table('meeting_vote_responses', function (Blueprint $table) {
            $table->unsignedBigInteger('meeting_participant_id')->nullable()->change();
        });

        // Recreate FK on participant_id (nullable now).
        if (! $fkExists('meeting_participant_id')) {
            Schema::table('meeting_vote_responses', function (Blueprint $table) {
                $table->foreign('meeting_participant_id')
                    ->references('id')->on('meeting_participants')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_vote_responses')) {
            return;
        }

        Schema::table('meeting_vote_responses', function (Blueprint $table) {
            $table->dropUnique('meeting_vote_resp_topic_user_unique');
        });
        Schema::table('meeting_vote_responses', function (Blueprint $table) {
            $table->unique(['meeting_vote_topic_id', 'meeting_participant_id'], 'meeting_vote_resp_topic_part_unique');
            $table->unsignedBigInteger('meeting_participant_id')->nullable(false)->change();
        });
        if (Schema::hasColumn('meeting_vote_responses', 'user_id')) {
            Schema::table('meeting_vote_responses', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropIndex('mvr_user_idx');
                $table->dropColumn('user_id');
            });
        }
    }
};
