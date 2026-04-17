<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_assignment_reminders')) {
            $count = DB::table('task_assignment_reminders')->count();
            if ($count > 0) {
                throw new \RuntimeException("task_assignment_reminders has {$count} rows — refusing to drop. Manual migration needed.");
            }
        }

        Schema::dropIfExists('task_assignment_reminders');

        Schema::create('task_assignment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id')->constrained('task_assignment_items')->cascadeOnDelete();
            $table->foreignId('notification_schedule_id')->constrained('notification_schedules')->cascadeOnDelete();
            $table->enum('moment', ['before', 'on', 'after']);
            $table->dateTime('remind_at');
            $table->enum('status', ['pending', 'fired', 'cancelled'])->default('pending');
            $table->dateTime('fired_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_reminders');
    }
};
