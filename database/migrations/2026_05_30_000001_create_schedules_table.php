<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->enum('module_type', ['EXECUTIVE', 'OFFICE'])->default('OFFICE');
            $table->string('title', 500);
            $table->text('content')->nullable();
            $table->string('location', 500)->nullable();
            $table->enum('session', ['MORNING', 'AFTERNOON', 'EVENING', 'ALL_DAY'])->default('MORNING');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->unsignedBigInteger('driver_user_id')->nullable();
            $table->string('preparation_location', 500)->nullable();

            $table->enum('status', ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('DRAFT');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_note')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_rule')->nullable();
            $table->unsignedBigInteger('parent_schedule_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            // Foreign keys
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('host_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('driver_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_schedule_id')->references('id')->on('schedules')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['organization_id', 'date'], 'idx_org_date');
            $table->index(['organization_id', 'module_type', 'date'], 'idx_org_module_date');
            $table->index(['organization_id', 'status'], 'idx_org_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
