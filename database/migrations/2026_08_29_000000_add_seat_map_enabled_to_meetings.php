<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meetings.seat_map_enabled — bật tab "Sơ đồ chỗ ngồi" ở trang chi tiết cuộc họp.
 *
 * Mặc định false: cuộc họp cũ (và cuộc họp không cần xếp chỗ) không hiện tab.
 * Thư ký bật cờ này ở form tạo/sửa khi có bố trí chỗ ngồi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && ! Schema::hasColumn('meetings', 'seat_map_enabled')) {
            $after = Schema::hasColumn('meetings', 'internal_chat_enabled') ? 'internal_chat_enabled' : 'attendance_locked';
            Schema::table('meetings', function (Blueprint $table) use ($after) {
                $table->boolean('seat_map_enabled')->default(false)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meetings') && Schema::hasColumn('meetings', 'seat_map_enabled')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropColumn('seat_map_enabled');
            });
        }
    }
};
