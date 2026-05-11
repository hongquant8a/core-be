<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng meeting_personal_notes để chair/operator note được (không cần participant row).
 *  - Thêm user_id (NOT NULL sau backfill).
 *  - Cho phép meeting_participant_id nullable (chair/op không có row participant).
 *  - Backfill user_id từ participant.attendee.user_id cho note hiện có.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_personal_notes')) {
            return;
        }

        // Thêm user_id nullable trước, backfill, rồi đặt NOT NULL.
        if (! Schema::hasColumn('meeting_personal_notes', 'user_id')) {
            Schema::table('meeting_personal_notes', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('meeting_id');
                $table->index('user_id', 'mpn_user_idx');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });

            // Backfill từ participant.attendee.user_id.
            DB::statement('
                UPDATE meeting_personal_notes n
                INNER JOIN meeting_participants p ON p.id = n.meeting_participant_id
                INNER JOIN meeting_attendees a ON a.id = p.meeting_attendee_id
                SET n.user_id = a.user_id
                WHERE n.user_id IS NULL
            ');
        }

        // participant_id -> nullable (chair/op không có participant row).
        Schema::table('meeting_personal_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('meeting_participant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('meeting_personal_notes')) {
            if (Schema::hasColumn('meeting_personal_notes', 'user_id')) {
                Schema::table('meeting_personal_notes', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                    $table->dropIndex('mpn_user_idx');
                    $table->dropColumn('user_id');
                });
            }
            Schema::table('meeting_personal_notes', function (Blueprint $table) {
                $table->unsignedBigInteger('meeting_participant_id')->nullable(false)->change();
            });
        }
    }
};
