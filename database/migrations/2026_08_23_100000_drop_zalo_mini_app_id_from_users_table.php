<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gỡ cột zalo_mini_app_id khỏi users.
 *
 * Đăng nhập qua Zalo Mini App đã bị loại bỏ khi miniapp chuyển sang PWA — không còn
 * code nào đọc hay ghi cột này. Giữ lại chỉ làm người đọc schema tưởng tính năng vẫn
 * còn.
 *
 * Phải dropUnique trước dropColumn: unique index do migration
 * 2026_08_21_000000 tạo ra, MySQL không cho bỏ cột khi index còn tham chiếu tới nó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['zalo_mini_app_id']);
            $table->dropColumn('zalo_mini_app_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('zalo_mini_app_id', 128)->nullable()->after('zalo_user_id');
            $table->unique('zalo_mini_app_id');
        });
    }
};
