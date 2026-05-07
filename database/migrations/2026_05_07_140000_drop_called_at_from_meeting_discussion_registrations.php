<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop called_at column khỏi meeting_discussion_registrations.
 * State machine đơn giản hóa thành nhị phân: registered → completed (không còn "called").
 * Operator đánh dấu trực tiếp completed_at, không qua trạng thái trung gian.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_discussion_registrations')
            && Schema::hasColumn('meeting_discussion_registrations', 'called_at')) {
            Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
                $table->dropColumn('called_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meeting_discussion_registrations')
            && ! Schema::hasColumn('meeting_discussion_registrations', 'called_at')) {
            Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
                $table->dateTime('called_at')->nullable()->after('status');
            });
        }
    }
};
