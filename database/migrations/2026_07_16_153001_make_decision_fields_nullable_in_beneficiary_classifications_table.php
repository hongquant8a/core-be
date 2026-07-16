<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiary_classifications', function (Blueprint $table) {
            // Cho phép ghi nhận loại đối tượng trước, bổ sung số/ngày quyết định + cơ quan
            // ban hành sau khi cán bộ có đủ giấy tờ — tránh chặn nhập liệu khi chưa có hồ sơ đầy đủ.
            $table->string('decision_no')->nullable()->change();
            $table->date('decision_date')->nullable()->change();
            $table->string('issued_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('beneficiary_classifications', function (Blueprint $table) {
            $table->string('decision_no')->nullable(false)->change();
            $table->date('decision_date')->nullable(false)->change();
            $table->string('issued_by')->nullable(false)->change();
        });
    }
};
