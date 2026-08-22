<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gỡ cột zalo_mini_app_id: toàn bộ luồng đăng nhập im lặng qua Zalo Mini App
 * (AuthService::loginZalo, POST /auth/zalo-login) đã bị loại bỏ nên cột này
 * không còn nơi nào đọc/ghi.
 *
 * Drop unique index trước rồi mới drop cột để không phụ thuộc hành vi tự gỡ
 * index của từng driver.
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
            $table->string('zalo_mini_app_id', 128)->nullable()->after('zalo_user_id')->unique();
        });
    }
};
