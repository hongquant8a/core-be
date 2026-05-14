<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khách mời PER MEETING (KHÔNG phải catalog org-level). Mỗi meeting có list khách
 * mời riêng — admin nhập thông tin trực tiếp ở form tạo/sửa meeting. Khách mời
 * không có user account, không có chuyện đăng nhập, chỉ dùng để gửi thư mời
 * (email/SMS) khi meeting publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('name');                    // Họ tên
            $table->string('position_name')->nullable(); // Chức vụ
            $table->string('phone', 30);                // Số điện thoại
            $table->string('email');                    // Địa chỉ email
            $table->string('organization_name')->nullable(); // Đơn vị (text tự nhập, khác organization_id)
            $table->dateTime('invited_at')->nullable(); // Lần gần nhất gửi thư mời thành công
            $table->timestamps();

            $table->index(['meeting_id']);
            $table->index(['organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_guests');
    }
};
