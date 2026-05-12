<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm `operator_note` cho meeting_discussion_registrations.
 * Operator/Chair cập nhật sau khi đại biểu phát biểu xong:
 *  - type=discussion -> ghi chú thảo luận
 *  - type=question   -> nội dung trả lời chất vấn
 * Đại biểu KHÔNG sửa được field này (gate ở service).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_discussion_registrations')) {
            return;
        }
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_discussion_registrations', 'operator_note')) {
                $table->text('operator_note')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_discussion_registrations')) {
            return;
        }
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_discussion_registrations', 'operator_note')) {
                $table->dropColumn('operator_note');
            }
        });
    }
};
