<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting runtime state — operator điều khiển trong phiên họp:
 *  - runtime_started_at: bắt đầu phiên họp (tách với start_time đã dự kiến lúc tạo).
 *  - runtime_paused_at: tạm dừng. /start sau pause = clear paused_at (resume).
 *  - runtime_ended_at: kết thúc thủ công (khác end_time đã dự kiến).
 *
 * FE derive trạng thái:
 *   if ended_at != null    → ended
 *   if paused_at != null   → paused
 *   if started_at != null  → in_progress
 *   else                   → not_started (dù start_time đã đến)
 *
 * 3 timestamp này KHÔNG ghi đè status (draft/published/cancelled) — đó là lifecycle
 * khác. Runtime state chỉ track manual operator actions trong phiên.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'runtime_started_at')) {
                $table->dateTime('runtime_started_at')->nullable()->after('attendance_locked');
            }
            if (! Schema::hasColumn('meetings', 'runtime_paused_at')) {
                $table->dateTime('runtime_paused_at')->nullable()->after('runtime_started_at');
            }
            if (! Schema::hasColumn('meetings', 'runtime_ended_at')) {
                $table->dateTime('runtime_ended_at')->nullable()->after('runtime_paused_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            foreach (['runtime_ended_at', 'runtime_paused_at', 'runtime_started_at'] as $col) {
                if (Schema::hasColumn('meetings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
