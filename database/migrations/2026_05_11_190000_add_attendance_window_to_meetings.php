<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung giờ điểm danh — đại biểu chỉ được bấm điểm danh trong khoảng này.
 *   - attendance_open_at: thời điểm mở cửa điểm danh (vd 30 phút trước start_time)
 *   - attendance_close_at: thời điểm đóng cửa điểm danh (vd start_time + 15 phút)
 * Null = không giới hạn (cho phép bất kỳ lúc nào sau publish).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'attendance_open_at')) {
                $table->dateTime('attendance_open_at')->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('meetings', 'attendance_close_at')) {
                $table->dateTime('attendance_close_at')->nullable()->after('attendance_open_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }
        Schema::table('meetings', function (Blueprint $table) {
            foreach (['attendance_open_at', 'attendance_close_at'] as $col) {
                if (Schema::hasColumn('meetings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
