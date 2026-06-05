<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── schedule_reminders ────────────────────────────────────────────────
        Schema::table('schedule_reminders', function (Blueprint $table) {
            $table->renameColumn('minutes_before', 'offset_minutes');
        });

        Schema::table('schedule_reminders', function (Blueprint $table) {
            $table->integer('offset_minutes')->nullable()->change();
            // immediate = bắn ngay khi schedule được publish (per-schedule, song song với module-level event)
            $table->enum('moment', ['immediate', 'before', 'on', 'after'])->default('before');
            $table->dateTime('remind_at')->nullable();
            $table->unsignedBigInteger('notification_schedule_id')->nullable();
            $table->enum('status', ['pending', 'fired', 'cancelled'])->default('pending');
            $table->dateTime('fired_at')->nullable();

            $table->foreign('notification_schedule_id')
                ->references('id')->on('notification_schedules')
                ->nullOnDelete();
        });

        DB::table('schedule_reminders')->update(['moment' => 'before']);

        // ── reminder_presets ──────────────────────────────────────────────────
        // Preset không có immediate — immediate chỉ dùng ở tầng per-record.
        Schema::table('reminder_presets', function (Blueprint $table) {
            $table->renameColumn('minutes_before', 'offset_minutes');
        });

        Schema::table('reminder_presets', function (Blueprint $table) {
            $table->integer('offset_minutes')->nullable()->change();
            $table->enum('moment', ['before', 'on', 'after'])->default('before');
        });

        DB::table('reminder_presets')->update(['moment' => 'before']);
    }

    public function down(): void
    {
        Schema::table('schedule_reminders', function (Blueprint $table) {
            $table->dropForeign(['notification_schedule_id']);
            $table->dropColumn(['moment', 'remind_at', 'notification_schedule_id', 'status', 'fired_at']);
            $table->integer('offset_minutes')->nullable(false)->change();
            $table->renameColumn('offset_minutes', 'minutes_before');
        });

        Schema::table('reminder_presets', function (Blueprint $table) {
            $table->dropColumn('moment');
            $table->integer('offset_minutes')->nullable(false)->change();
            $table->renameColumn('offset_minutes', 'minutes_before');
        });
    }
};
