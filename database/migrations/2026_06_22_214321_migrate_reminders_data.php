<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. TaskAssignmentItem reminders
        if (Schema::hasTable('task_assignment_reminders') && Schema::hasTable('task_assignment_documents')) {
            DB::statement("
                INSERT INTO reminders (
                    remindable_type, remindable_id, organization_id, reminder_type, source,
                    notification_schedule_id, moment, offset_minutes, remind_at, channels,
                    status, fired_at, created_at, updated_at
                )
                SELECT 
                    'task_assignment_item', 
                    r.task_assignment_item_id, 
                    d.organization_id, 
                    COALESCE(r.reminder_type, 'scheduled'), 
                    COALESCE(r.source, 'PRESET'),
                    r.notification_schedule_id, 
                    r.moment, 
                    r.offset_minutes, 
                    r.remind_at, 
                    r.channels,
                    COALESCE(r.status, 'pending'), 
                    r.fired_at, 
                    r.created_at, 
                    r.updated_at
                FROM task_assignment_reminders r
                LEFT JOIN task_assignment_documents d ON d.id = r.task_assignment_document_id
            ");
        }

        // 2. Meeting reminders
        if (Schema::hasTable('meeting_reminders')) {
            DB::statement("
                INSERT INTO reminders (
                    remindable_type, remindable_id, organization_id, reminder_type, source,
                    notification_schedule_id, moment, offset_minutes, remind_at, channels,
                    status, fired_at, message, created_by, created_at, updated_at
                )
                SELECT 
                    'meeting', 
                    meeting_id, 
                    organization_id, 
                    COALESCE(reminder_type, 'scheduled'), 
                    COALESCE(source, 'PRESET'),
                    notification_schedule_id, 
                    moment, 
                    offset_minutes, 
                    COALESCE(remind_at, scheduled_at), 
                    channels,
                    CASE WHEN status = 'sent' THEN 'fired' ELSE COALESCE(status, 'pending') END, 
                    COALESCE(fired_at, sent_at), 
                    message, 
                    created_by, 
                    created_at, 
                    updated_at
                FROM meeting_reminders
            ");
        }

        // 3. Schedule reminders
        if (Schema::hasTable('schedule_reminders') && Schema::hasTable('schedules')) {
            DB::statement("
                INSERT INTO reminders (
                    remindable_type, remindable_id, organization_id, reminder_type, source,
                    notification_schedule_id, moment, offset_minutes, remind_at, channels,
                    status, fired_at, created_at, updated_at
                )
                SELECT 
                    'schedule', 
                    r.schedule_id, 
                    s.organization_id, 
                    COALESCE(r.reminder_type, 'scheduled'), 
                    COALESCE(r.source, 'PRESET'),
                    r.notification_schedule_id, 
                    CASE WHEN r.moment = 'immediate' THEN NULL ELSE r.moment END, 
                    r.offset_minutes, 
                    r.remind_at, 
                    r.channels,
                    COALESCE(r.status, 'pending'), 
                    r.fired_at, 
                    r.created_at, 
                    r.created_at
                FROM schedule_reminders r
                LEFT JOIN schedules s ON s.id = r.schedule_id
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('reminders')->truncate();
    }
};
