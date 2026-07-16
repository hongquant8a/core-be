<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiary_households', function (Blueprint $table) {
            // Cho phép tạo hộ với thông tin cơ bản trước, bổ sung địa chỉ sau khi cán bộ
            // xác minh thực địa — tránh chặn nhập liệu khi chưa có đủ thông tin.
            $table->string('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('beneficiary_households', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
        });
    }
};
