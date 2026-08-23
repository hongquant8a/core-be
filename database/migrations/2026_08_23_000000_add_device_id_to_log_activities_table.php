<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm device_id vào nhật ký để phân biệt thiết bị.
     *
     * Giá trị lấy từ header X-Device-Id — do FE tự sinh và giữ trong localStorage,
     * cùng nguồn với fcm_tokens.device_id nên tra chéo được hai bảng. Nullable vì
     * request từ Postman/script/bản FE cũ không gửi header này.
     */
    public function up(): void
    {
        Schema::table('log_activities', function (Blueprint $table) {
            $table->string('device_id', 100)->nullable()->after('user_agent');

            // Truy vấn thực tế luôn là "thiết bị này đã làm gì, gần đây nhất trước"
            // → index ghép với created_at thay vì index đơn.
            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('log_activities', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'created_at']);
            $table->dropColumn('device_id');
        });
    }
};
