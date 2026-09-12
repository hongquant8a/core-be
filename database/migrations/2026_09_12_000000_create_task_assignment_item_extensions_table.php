<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_item_extensions', function (Blueprint $table) {
            $table->id();

            // Tên khoá ngoại đặt thủ công: tên Laravel tự sinh cho bảng này vượt
            // giới hạn 64 ký tự của MySQL — đúng vấn đề bảng điều chuyển đã gặp.
            $table->foreignId('task_assignment_item_id');
            $table->foreign('task_assignment_item_id', 'fk_ta_extensions_item')->references('id')->on('task_assignment_items')->cascadeOnDelete();

            $table->foreignId('requested_by_user_id')->nullable();
            $table->foreign('requested_by_user_id', 'fk_ta_extensions_requester')->references('id')->on('users')->nullOnDelete();

            // Hạn tại thời điểm xin. BẮT BUỘC chụp lại: sau khi duyệt thì
            // `items.end_at` đổi, không lưu hạn cũ thì dòng lịch sử mất nghĩa —
            // đọc "xin dời tới 20/01" mà không biết dời từ đâu.
            $table->dateTime('current_end_at')->nullable();
            $table->dateTime('requested_end_at');
            $table->text('reason');

            $table->string('status', 20)->default('pending');

            $table->foreignId('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id', 'fk_ta_extensions_reviewer')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->foreignId('organization_id')->nullable();
            $table->foreign('organization_id', 'fk_ta_extensions_org')->references('id')->on('organizations')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_assignment_item_id', 'status'], 'ta_item_extensions_item_id_status_index');
            $table->index(['status', 'created_at'], 'ta_item_extensions_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_item_extensions');
    }
};
