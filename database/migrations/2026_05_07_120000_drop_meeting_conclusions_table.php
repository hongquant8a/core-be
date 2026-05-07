<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ bảng meeting_conclusions — chuyển sang dùng meeting_documents để upload tài liệu
 * sau họp với loại "Tài liệu kết luận cuộc họp".
 *
 * Thư ký workflow mới: vào Tab Tài liệu, upload thêm record meeting_documents có
 * meeting_document_type = "Tài liệu kết luận cuộc họp". Citizen/đại biểu xem ở Tab
 * Tài liệu chung (filter theo is_public + meeting visibility).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meeting_conclusions');
    }

    public function down(): void
    {
        // Khôi phục bảng (rollback) — schema giữ nguyên cấu trúc gốc khi drop.
        Schema::create('meeting_conclusions', function ($table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
