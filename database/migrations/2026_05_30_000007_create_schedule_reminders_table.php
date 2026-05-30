<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('reminder_preset_id')->nullable();
            $table->string('source')->default('preset'); // preset, custom
            $table->string('moment')->default('before'); // before, on, after
            $table->integer('offset_minutes')->default(0);
            $table->dateTime('remind_at');
            $table->json('channels'); // ['fcm', 'zalo', 'sms', 'inapp']
            $table->tinyInteger('status')->default(0); // 0=PENDING, 1=SENT, 2=FAILED
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('reminder_preset_id')->references('id')->on('reminder_presets')->nullOnDelete();

            $table->index(['schedule_id'], 'idx_reminder_schedule');
            $table->index(['status', 'remind_at'], 'idx_reminder_status_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_reminders');
    }
};
