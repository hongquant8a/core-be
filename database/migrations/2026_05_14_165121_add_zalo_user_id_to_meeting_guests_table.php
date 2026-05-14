<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung zalo_user_id cho khách mời — dùng để gửi qua Zalo OA channel.
 * Optional (chỉ gửi Zalo nếu admin nhập được zalo_user_id của khách).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_guests', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_guests', 'zalo_user_id')) {
                $table->string('zalo_user_id', 64)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_guests', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_guests', 'zalo_user_id')) {
                $table->dropColumn('zalo_user_id');
            }
        });
    }
};
