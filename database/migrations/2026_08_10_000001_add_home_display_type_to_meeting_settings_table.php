<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meeting_settings.home_display_type — cấu hình giao diện trang chủ cuộc họp:
 * 'status_type' (theo trạng thái) hoặc 'meeting_type' (theo loại cuộc họp).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_settings') && ! Schema::hasColumn('meeting_settings', 'home_display_type')) {
            $after = Schema::hasColumn('meeting_settings', 'allow_host_management') ? 'allow_host_management' : 'qr_icon_media_id';
            Schema::table('meeting_settings', function (Blueprint $table) use ($after) {
                $table->string('home_display_type')->default('status_type')->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meeting_settings') && Schema::hasColumn('meeting_settings', 'home_display_type')) {
            Schema::table('meeting_settings', function (Blueprint $table) {
                $table->dropColumn('home_display_type');
            });
        }
    }
};
