<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor attendee ↔ group sang many-to-many.
 *
 * Trước: meeting_attendees.meeting_attendee_group_id (FK đơn) → 1 attendee 1 group.
 * Sau: pivot meeting_attendee_group_members → 1 attendee N groups.
 *
 * Backfill: copy data hiện có (meeting_attendee_group_id NOT NULL) vào pivot
 * trước khi drop column gốc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_attendees')) {
            return;
        }

        // 1. Tạo pivot.
        if (! Schema::hasTable('meeting_attendee_group_members')) {
            Schema::create('meeting_attendee_group_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_attendee_group_id')->constrained('meeting_attendee_groups')->cascadeOnDelete();
                $table->foreignId('meeting_attendee_id')->constrained('meeting_attendees')->cascadeOnDelete();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(
                    ['meeting_attendee_group_id', 'meeting_attendee_id'],
                    'meeting_attendee_group_members_unique'
                );
                $table->index(['organization_id', 'meeting_attendee_group_id'], 'meeting_attendee_group_members_org_idx');
            });
        }

        // 2. Backfill data hiện có.
        if (Schema::hasColumn('meeting_attendees', 'meeting_attendee_group_id')) {
            $rows = DB::table('meeting_attendees')
                ->whereNotNull('meeting_attendee_group_id')
                ->get(['id', 'meeting_attendee_group_id', 'organization_id']);

            $now = now();
            foreach ($rows as $row) {
                DB::table('meeting_attendee_group_members')->insertOrIgnore([
                    'meeting_attendee_group_id' => $row->meeting_attendee_group_id,
                    'meeting_attendee_id' => $row->id,
                    'organization_id' => $row->organization_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 3. Drop FK + column trên attendees.
        if (Schema::hasColumn('meeting_attendees', 'meeting_attendee_group_id')) {
            Schema::table('meeting_attendees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('meeting_attendee_group_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_attendees')) {
            return;
        }

        // Re-add FK column trên attendees.
        if (! Schema::hasColumn('meeting_attendees', 'meeting_attendee_group_id')) {
            Schema::table('meeting_attendees', function (Blueprint $table) {
                $table->foreignId('meeting_attendee_group_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('meeting_attendee_groups')
                    ->nullOnDelete();
            });

            // Restore: lấy group đầu tiên trong pivot làm primary group cho mỗi attendee.
            $rows = DB::table('meeting_attendee_group_members')
                ->select('meeting_attendee_id', DB::raw('MIN(meeting_attendee_group_id) AS primary_group_id'))
                ->groupBy('meeting_attendee_id')
                ->get();
            foreach ($rows as $row) {
                DB::table('meeting_attendees')
                    ->where('id', $row->meeting_attendee_id)
                    ->update(['meeting_attendee_group_id' => $row->primary_group_id]);
            }
        }

        Schema::dropIfExists('meeting_attendee_group_members');
    }
};
