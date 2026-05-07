<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ 3 column runtime state — quyết định 2026-05-07 chuyển spec:
 * Tab 7 không còn nút Bắt đầu / Tạm dừng / Kết thúc runtime nữa. FE phase
 * vẫn derive từ `start_time` + `end_time` vs `now()` như cũ.
 *
 * Chỉ giữ 1 nút "Kết thúc sớm" — endpoint mới /end-early sẽ update `end_time = now()`
 * (xem migration cùng commit + endpoint mới trong MeetingService).
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
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
};
