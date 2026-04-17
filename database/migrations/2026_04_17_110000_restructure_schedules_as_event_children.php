<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restructure:
     * - Schedules trở thành child của event_config (FK notification_event_config_id)
     * - Schedule có channels JSON (migrated)
     * - Moment + offset nullable (null = instant schedule cho non-reminder event)
     * - Bỏ module_key trên schedule (derive qua event_config)
     */
    public function up(): void
    {
        // Clean existing schedules data (not production yet). Also kill dependent reminders.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('task_assignment_reminders')->delete();
        DB::table('notification_schedules')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->foreignId('notification_event_config_id')
                ->nullable()
                ->after('id')
                ->constrained('notification_event_configs')
                ->cascadeOnDelete();
            $table->json('channels')->nullable()->after('offset_minutes');
            $table->dropIndex('notif_sched_module_idx');
            $table->dropColumn('module_key');
        });

        // Make moment nullable — use raw SQL to avoid doctrine/dbal dependency
        DB::statement("ALTER TABLE notification_schedules MODIFY COLUMN moment ENUM('before','on','after') NULL");

        // Drop channels from event_configs — channels giờ thuộc schedule
        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->dropColumn('channels');
        });
    }

    public function down(): void
    {
        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->json('channels')->nullable()->after('enabled');
        });

        DB::statement("ALTER TABLE notification_schedules MODIFY COLUMN moment ENUM('before','on','after') NOT NULL");

        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->string('module_key', 50)->nullable()->after('id');
            $table->index('module_key', 'notif_sched_module_idx');
            $table->dropForeign(['notification_event_config_id']);
            $table->dropColumn(['notification_event_config_id', 'channels']);
        });
    }
};
