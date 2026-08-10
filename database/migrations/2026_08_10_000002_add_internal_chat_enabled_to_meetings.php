<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meetings.internal_chat_enabled — bật tab "Trao đổi" (chat nhóm nội bộ) cho cuộc họp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && ! Schema::hasColumn('meetings', 'internal_chat_enabled')) {
            $after = Schema::hasColumn('meetings', 'auto_confirm_attendance') ? 'auto_confirm_attendance' : 'attendance_locked';
            Schema::table('meetings', function (Blueprint $table) use ($after) {
                $table->boolean('internal_chat_enabled')->default(false)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meetings') && Schema::hasColumn('meetings', 'internal_chat_enabled')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropColumn('internal_chat_enabled');
            });
        }
    }
};
