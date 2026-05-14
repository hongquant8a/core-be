<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Background riêng cho từng cuộc họp (Tab 8 màn chiếu). Nếu meeting không cấu hình
 * field này thì FE fallback sang `MeetingSetting.projector_image_media_id` (mặc định
 * của tổ chức).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'projector_image_media_id')) {
                $table->unsignedBigInteger('projector_image_media_id')
                    ->nullable()
                    ->after('qr_manager_user_id');
                $table->index('projector_image_media_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'projector_image_media_id')) {
                $table->dropIndex(['projector_image_media_id']);
                $table->dropColumn('projector_image_media_id');
            }
        });
    }
};
