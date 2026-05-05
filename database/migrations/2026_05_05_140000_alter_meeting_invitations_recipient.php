<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * meeting_invitations: cho phép chủ trì + thư ký nhận giấy mời trực tiếp qua attendee_id
 * (không cần qua bảng participants). Hai nguồn recipient:
 *   - meeting_participant_id (đại biểu mời thường) — nullable
 *   - meeting_attendee_id    (chủ trì + thư ký, FK trên meetings) — nullable, mới
 * Constraint: 1 trong 2 phải có (enforce ở app layer).
 *
 * Defensive: dùng information_schema để check FK + column tồn tại nhằm idempotent
 * (chạy lại trên DB đã có schema mới cũng không lỗi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_invitations')) {
            return;
        }

        $fkName = 'meeting_invitations_meeting_participant_id_foreign';

        // 1) Drop FK cũ (nếu còn) — phải drop trước khi MODIFY column.
        if ($this->foreignKeyExists('meeting_invitations', $fkName)) {
            Schema::table('meeting_invitations', function (Blueprint $table) {
                $table->dropForeign('meeting_invitations_meeting_participant_id_foreign');
            });
        }

        // 2) MODIFY nullable. Idempotent — chạy lại trên cột đã NULL không lỗi.
        DB::statement('ALTER TABLE `meeting_invitations` MODIFY `meeting_participant_id` BIGINT UNSIGNED NULL');

        // 3) Re-add FK cascadeOnDelete.
        if (! $this->foreignKeyExists('meeting_invitations', $fkName)) {
            Schema::table('meeting_invitations', function (Blueprint $table) {
                $table->foreign('meeting_participant_id')
                    ->references('id')->on('meeting_participants')
                    ->cascadeOnDelete();
            });
        }

        // 4) Thêm cột meeting_attendee_id nullable + FK.
        if (! Schema::hasColumn('meeting_invitations', 'meeting_attendee_id')) {
            Schema::table('meeting_invitations', function (Blueprint $table) {
                $table->foreignId('meeting_attendee_id')
                    ->nullable()
                    ->after('meeting_participant_id')
                    ->constrained('meeting_attendees')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_invitations')) {
            return;
        }

        if (Schema::hasColumn('meeting_invitations', 'meeting_attendee_id')) {
            Schema::table('meeting_invitations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('meeting_attendee_id');
            });
        }

        // Khôi phục NOT NULL — chỉ chạy được nếu đã không còn dữ liệu invitation chỉ-có-attendee_id.
        DB::statement('ALTER TABLE `meeting_invitations` MODIFY `meeting_participant_id` BIGINT UNSIGNED NOT NULL');
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(1) AS cnt FROM information_schema.table_constraints
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = "FOREIGN KEY"',
            [$database, $table, $constraintName]
        );

        return ($row->cnt ?? 0) > 0;
    }
};
