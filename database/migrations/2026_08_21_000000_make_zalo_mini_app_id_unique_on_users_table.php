<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * zalo_mini_app_id là danh tính đăng nhập của Zalo Mini App nên bắt buộc phải unique:
 * nếu hai user cùng giữ một ID thì truy vấn đăng nhập im lặng sẽ trả về user không
 * xác định. Cột đang rỗng hoàn toàn (chưa code nào ghi vào) nên thêm unique an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['zalo_mini_app_id']);
            $table->unique('zalo_mini_app_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['zalo_mini_app_id']);
            $table->index('zalo_mini_app_id');
        });
    }
};
