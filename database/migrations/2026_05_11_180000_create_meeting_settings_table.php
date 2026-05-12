<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cấu hình cuộc họp — singleton 1 row / 1 organization.
 * 3 trường ảnh: hình màn trình chiếu, chữ ký chủ tọa, icon center QR code.
 * Tất cả lưu qua Spatie media library (media_id FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('projector_image_media_id')->nullable();
            $table->unsignedBigInteger('chairperson_signature_media_id')->nullable();
            $table->unsignedBigInteger('qr_icon_media_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('projector_image_media_id');
            $table->index('chairperson_signature_media_id');
            $table->index('qr_icon_media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_settings');
    }
};
