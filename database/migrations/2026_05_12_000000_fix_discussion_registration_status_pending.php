<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix data: meeting_discussion_registrations.status = 'pending' (legacy/seed sai)
 * → 'registered' (giá trị hợp lệ theo MeetingDiscussionStatusEnum).
 *
 * Enum chỉ có 2 case: Registered='registered' và Completed='completed'.
 * Status 'pending' không hợp lệ → service complete() từ chối transition → FE bị 422.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('meeting_discussion_registrations')
            ->where('status', 'pending')
            ->update(['status' => 'registered']);
    }

    public function down(): void
    {
        // No-op: 'pending' là invalid value, không revert ngược lại.
    }
};
