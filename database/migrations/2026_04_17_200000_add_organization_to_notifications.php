<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add organization_id NOT NULL to notifications + notification_event_configs.
     * Schedules inherit org via FK to event_config (no direct column).
     */
    public function up(): void
    {
        // Clear existing data — no production yet (user confirmed)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('task_assignment_reminders')->delete();
        DB::table('notification_deliveries')->delete();
        DB::table('notifications')->delete();
        DB::table('notification_schedules')->delete();
        DB::table('notification_event_configs')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->after('user_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index(['organization_id', 'user_id'], 'notif_org_user_idx');
        });

        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->dropUnique('notif_event_cfg_module_event_unique');
            $table->foreignId('organization_id')
                ->after('module_key')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->unique(['module_key', 'event_key', 'organization_id'], 'notif_event_cfg_module_event_org_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notif_org_user_idx');
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->dropUnique('notif_event_cfg_module_event_org_unique');
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->unique(['module_key', 'event_key'], 'notif_event_cfg_module_event_unique');
        });
    }
};
