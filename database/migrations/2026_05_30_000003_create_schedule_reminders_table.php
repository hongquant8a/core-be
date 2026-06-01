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
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('notification_schedule_id')->nullable();
            $table->enum('reminder_type', ['PRESET', 'CUSTOM'])->default('PRESET');
            $table->enum('moment', ['BEFORE', 'ON', 'AFTER'])->nullable();
            $table->integer('offset_minutes')->nullable();
            $table->json('channels')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->enum('status', ['PENDING', 'SENT', 'FAILED', 'CANCELLED'])->default('PENDING');
            $table->text('message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('notification_schedule_id')->references('id')->on('notification_schedules')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('schedule_id', 'idx_schedule');
            $table->index(['status', 'scheduled_at'], 'idx_status_scheduled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_reminders');
    }
};
