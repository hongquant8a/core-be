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
        // 1. Drop existing legacy tables
        Schema::dropIfExists('schedule_participants');
        Schema::dropIfExists('schedule_reminders');

        // 2. Modify schedules table to match new spec
        Schema::table('schedules', function (Blueprint $table) {
            // Drop foreign keys
            try { $table->dropForeign(['organization_id']); } catch (\Exception $e) {}
            try { $table->dropForeign(['host_user_id']); } catch (\Exception $e) {}
            try { $table->dropForeign(['driver_user_id']); } catch (\Exception $e) {}
            try { $table->dropForeign(['approved_by']); } catch (\Exception $e) {}
            try { $table->dropForeign(['created_by']); } catch (\Exception $e) {}
            try { $table->dropForeign(['updated_by']); } catch (\Exception $e) {}

            // Drop old indexes
            try { $table->dropIndex('idx_org_date'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_org_module_date'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_org_status'); } catch (\Exception $e) {}

            // Rename key columns
            $table->renameColumn('date', 'event_date');
            $table->renameColumn('host_user_id', 'host_id');
            $table->renameColumn('driver_user_id', 'driver_id');
        });

        // Drop redundant/modified columns
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['title', 'rejection_note', 'session', 'status']);
        });

        // Re-add session, status, and new columns with correct types
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('session', ['S', 'C', 'T'])->after('end_time');
            $table->tinyInteger('status')->default(0)->after('sort_order'); // 0=DRAFT, 1=PENDING, 2=PUBLISHED, 3=CANCELLED
            
            $table->string('host_text', 255)->nullable()->after('host_id');
            $table->string('driver_text', 255)->nullable()->after('driver_id');
            
            $table->tinyInteger('host_priority_weight')->unsigned()->default(99)->after('host_id');
            $table->string('participant_count', 50)->nullable()->after('preparation_unit');
            $table->tinyInteger('week_number')->unsigned()->default(1)->after('status');
            $table->smallInteger('year')->unsigned()->default(2026)->after('week_number');
            
            // color_code default
            $table->string('color_code', 7)->default('#FFFFFF')->change();

            // Re-establish foreign keys with new names
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('host_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Re-create indexes
            $table->index(['organization_id', 'module_type', 'event_date', 'session', 'status'], 'idx_org_module_date');
            $table->index(['organization_id', 'year', 'week_number'], 'idx_org_week');
            $table->index(['organization_id', 'host_id', 'event_date'], 'idx_org_host');
            $table->index(['organization_id', 'driver_id', 'event_date'], 'idx_org_driver');
            $table->index(['organization_id', 'event_date', 'session', 'sort_order'], 'idx_sort');
        });

        // 3. Create new tables according to the specifications

        // notification_groups
        Schema::create('notification_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // notification_group_members
        Schema::create('notification_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');

            $table->foreign('group_id')->references('id')->on('notification_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['group_id', 'user_id']);
        });

        // schedule_notification_recipients
        Schema::create('schedule_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('notification_groups')->cascadeOnDelete();
        });

        // reminder_presets
        Schema::create('reminder_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name', 100);
            $table->integer('minutes_before');
            $table->json('channels');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        // schedule_reminders
        Schema::create('schedule_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->integer('minutes_before');
            $table->json('channels');
            $table->enum('source', ['PRESET', 'CUSTOM']);
            $table->unsignedBigInteger('preset_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('preset_id')->references('id')->on('reminder_presets')->nullOnDelete();
        });

        // schedule_notifications
        Schema::create('schedule_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('channel', ['FCM', 'ZALO', 'SMS', 'APP']);
            $table->dateTime('remind_at');
            $table->tinyInteger('status')->default(0); // 0=PENDING, 1=SENT, 2=FAILED, 3=CANCELLED
            $table->tinyInteger('retry_count')->default(0);
            $table->string('external_message_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['organization_id', 'status', 'remind_at'], 'idx_org_status_remind');
            $table->index('schedule_id', 'idx_schedule_id');
            $table->index(['user_id', 'status'], 'idx_user_status');
        });

        // org_scheduling_settings
        Schema::create('org_scheduling_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->boolean('executive_requires_approval')->default(false);
            $table->json('executive_approver_roles')->nullable();
            $table->boolean('office_requires_approval')->default(false);
            $table->json('office_approver_roles')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        // schedule_attachments
        Schema::create('schedule_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->string('title', 255);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('created_at')->nullable();

            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_attachments');
        Schema::dropIfExists('org_scheduling_settings');
        Schema::dropIfExists('schedule_notifications');
        Schema::dropIfExists('schedule_reminders');
        Schema::dropIfExists('reminder_presets');
        Schema::dropIfExists('schedule_notification_recipients');
        Schema::dropIfExists('notification_group_members');
        Schema::dropIfExists('notification_groups');
        
        // Revert schedule renames and additions if needed, but since it's a migrate fresh environment, we skip down details.
    }
};
