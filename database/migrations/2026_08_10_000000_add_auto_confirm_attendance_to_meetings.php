<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meetings.auto_confirm_attendance — khi true, đại biểu tự điểm danh sẽ được duyệt
 * (present) ngay lập tức, không cần điều hành/thư ký approve thủ công.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && ! Schema::hasColumn('meetings', 'auto_confirm_attendance')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->boolean('auto_confirm_attendance')->default(false)->after('attendance_locked');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meetings') && Schema::hasColumn('meetings', 'auto_confirm_attendance')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropColumn('auto_confirm_attendance');
            });
        }
    }
};
