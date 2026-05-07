<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meetings.attendance_locked — operator chốt danh sách điểm danh.
 * Sau khi locked, đại biểu không tự checkin/báo vắng được nữa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && ! Schema::hasColumn('meetings', 'attendance_locked')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->boolean('attendance_locked')->default(false)->after('published_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meetings') && Schema::hasColumn('meetings', 'attendance_locked')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropColumn('attendance_locked');
            });
        }
    }
};
