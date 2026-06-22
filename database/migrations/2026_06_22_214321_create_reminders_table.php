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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation
            $table->string('remindable_type');
            $table->unsignedBigInteger('remindable_id');
            $table->index(['remindable_type', 'remindable_id'], 'idx_remindable');

            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();

            $table->string('reminder_type', 20)->default('scheduled');
            $table->string('source', 10)->default('PRESET');

            $table->foreignId('notification_schedule_id')->nullable()->constrained('notification_schedules')->nullOnDelete();

            $table->string('moment', 10)->nullable();
            $table->integer('offset_minutes')->nullable();
            $table->dateTime('remind_at')->nullable();

            $table->json('channels')->nullable();

            $table->string('status', 20)->default('pending');
            $table->dateTime('fired_at')->nullable();

            // Dành cho Meeting manual reminder
            $table->text('message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'remind_at'], 'idx_status_remind_at');
            $table->index(['organization_id', 'status'], 'idx_org_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
