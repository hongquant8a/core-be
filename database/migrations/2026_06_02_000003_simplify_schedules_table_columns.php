<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop indexes individually in try-catch blocks
        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_org_module_date');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_org_host');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_org_driver');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_sort');
            });
        } catch (\Exception $e) {}

        // Drop newly added indexes in case of rerun
        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_org_module_datetime');
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_org_host_datetime');
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_org_driver_datetime');
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('idx_sort_datetime');
            });
        } catch (\Exception $e) {}

        Schema::table('schedules', function (Blueprint $table) {
            // Drop physical columns if they exist
            $columnsToDrop = ['event_date', 'start_time', 'end_time', 'host_priority_weight', 'color_code'];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('schedules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'date_time')) {
                $table->dateTime('date_time')->nullable()->after('session');
            }
            
            // Re-add participants_text if it was accidentally dropped
            if (!Schema::hasColumn('schedules', 'participants_text')) {
                $table->text('participants_text')->nullable()->after('preparation_unit');
            }

            // Re-create indexes
            try { $table->index(['organization_id', 'module_type', 'date_time', 'session', 'status'], 'idx_org_module_datetime'); } catch (\Exception $e) {}
            try { $table->index(['organization_id', 'host_id', 'date_time'], 'idx_org_host_datetime'); } catch (\Exception $e) {}
            try { $table->index(['organization_id', 'driver_id', 'date_time'], 'idx_org_driver_datetime'); } catch (\Exception $e) {}
            try { $table->index(['organization_id', 'date_time', 'session', 'sort_order'], 'idx_sort_datetime'); } catch (\Exception $e) {}
        });

        // Add display_name to schedule_notification_recipients
        Schema::table('schedule_notification_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('schedule_notification_recipients', 'display_name')) {
                $table->string('display_name', 255)->nullable()->after('group_id');
            }
        });
    }

    public function down(): void
    {
        // Not needed
    }
};
