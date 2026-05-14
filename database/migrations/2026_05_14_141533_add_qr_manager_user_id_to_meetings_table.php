<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Người được giao "bật QR điểm danh" của 1 meeting cụ thể. Người này có thể là:
 * chủ trì, thư ký, đại biểu hoặc bất kỳ user nào cùng tổ chức (admin tạo meeting
 * lựa chọn). Khi field này set → user đó có quyền truy cập tab QR — bổ sung
 * vào logic chair/op của MeetingPolicy::showQrCode (không qua Spatie permission).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'qr_manager_user_id')) {
                $table->foreignId('qr_manager_user_id')
                    ->nullable()
                    ->after('current_meeting_discussion_registration_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'qr_manager_user_id')) {
                $table->dropConstrainedForeignId('qr_manager_user_id');
            }
        });
    }
};
