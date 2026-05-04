<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_reminders', function (Blueprint $table) {
            $table->foreignId('notification_schedule_id')
                ->nullable()
                ->after('reminder_type')
                ->constrained('notification_schedules')
                ->nullOnDelete();
            // moment: before|on|after cho reminder auto; null cho reminder thủ công.
            $table->string('moment')->nullable()->after('notification_schedule_id');
            $table->dateTime('fired_at')->nullable()->after('sent_at');

            $table->index(['status', 'scheduled_at'], 'meeting_remind_status_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_reminders', function (Blueprint $table) {
            $table->dropIndex('meeting_remind_status_at_idx');
            $table->dropConstrainedForeignId('notification_schedule_id');
            $table->dropColumn(['moment', 'fired_at']);
        });
    }
};
