<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schedules giờ chỉ định nghĩa "khi nào nhắc" (moment + offset).
     * Channels lấy từ event_config của reminder_{moment}. Bỏ enabled — xóa record nếu không muốn nhắc.
     */
    public function up(): void
    {
        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->dropColumn(['channels', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->json('channels')->nullable()->after('offset_minutes');
            $table->boolean('enabled')->default(true)->after('channels');
        });
    }
};
