<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // event_configs: drop polymorphic, add module_key
        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->dropIndex('notif_event_cfg_morph_idx');
            $table->dropUnique('notif_event_cfg_owner_event_unique');
            $table->dropColumn(['configurable_type', 'configurable_id']);
        });
        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->string('module_key', 50)->nullable()->after('id');
            $table->unique(['module_key', 'event_key'], 'notif_event_cfg_module_event_unique');
        });

        // schedules: drop polymorphic, add module_key
        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->dropIndex('notif_sched_morph_idx');
            $table->dropColumn(['configurable_type', 'configurable_id']);
        });
        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->string('module_key', 50)->nullable()->after('id');
            $table->index('module_key', 'notif_sched_module_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->dropUnique('notif_event_cfg_module_event_unique');
            $table->dropColumn('module_key');
        });
        Schema::table('notification_event_configs', function (Blueprint $table) {
            $table->string('configurable_type')->nullable();
            $table->unsignedBigInteger('configurable_id')->nullable();
            $table->index(['configurable_type', 'configurable_id'], 'notif_event_cfg_morph_idx');
            $table->unique(['configurable_type', 'configurable_id', 'event_key'], 'notif_event_cfg_owner_event_unique');
        });

        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->dropIndex('notif_sched_module_idx');
            $table->dropColumn('module_key');
        });
        Schema::table('notification_schedules', function (Blueprint $table) {
            $table->string('configurable_type')->nullable();
            $table->unsignedBigInteger('configurable_id')->nullable();
            $table->index(['configurable_type', 'configurable_id'], 'notif_sched_morph_idx');
        });
    }
};
