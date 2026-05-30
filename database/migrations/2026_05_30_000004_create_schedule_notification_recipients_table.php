<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->timestamps();

            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('notification_groups')->cascadeOnDelete();

            $table->index(['schedule_id', 'user_id'], 'idx_recip_schedule_user');
            $table->index(['schedule_id', 'group_id'], 'idx_recip_schedule_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_notification_recipients');
    }
};
