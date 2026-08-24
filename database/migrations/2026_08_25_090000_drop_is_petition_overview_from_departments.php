<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ cờ `is_petition_overview` trên phòng ban.
 *
 * Trước đây cờ này quyết định ai xem được toàn bộ đơn thư và ai được xóa / xóa
 * hàng loạt / đổi trạng thái hàng loạt / mở khóa — một phép AND ẩn nằm trong code,
 * khiến cấp quyền ở màn Vai trò mà người dùng vẫn nhận 403.
 *
 * Nay thay bằng quyền `task-assignment-petitions.viewAll`: quyền quyết định phạm vi,
 * màn phân quyền phản ánh đúng thứ người dùng làm được.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_departments', function (Blueprint $table) {
            $table->dropColumn('is_petition_overview');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_departments', function (Blueprint $table) {
            $table->boolean('is_petition_overview')->default(false)->after('sort_order');
        });
    }
};
