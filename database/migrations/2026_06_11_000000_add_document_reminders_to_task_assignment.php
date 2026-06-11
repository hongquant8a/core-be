<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_reminders', function (Blueprint $table) {
            $table->foreignId('task_assignment_document_id')
                ->nullable()
                ->after('id')
                ->constrained('task_assignment_documents')
                ->cascadeOnDelete();

            $table->string('reminder_type')->default('scheduled')->after('task_assignment_document_id');
        });

        Schema::table('task_assignment_reminders', function (Blueprint $table) {
            $table->foreignId('task_assignment_item_id')->nullable()->change();
            $table->foreignId('notification_schedule_id')->nullable()->change();
            $table->dateTime('remind_at')->nullable()->change();
        });

        // enum column — dùng raw query để cho nullable (DBAL không hỗ trợ enum change)
        DB::statement("ALTER TABLE `task_assignment_reminders` CHANGE `moment` `moment` VARCHAR(10) NULL");
        DB::statement("ALTER TABLE `task_assignment_reminders` CHANGE `status` `status` ENUM('pending','fired','cancelled','active') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // revert status về enum gốc (xóa 'active' khỏi enum)
        DB::statement("ALTER TABLE `task_assignment_reminders` CHANGE `status` `status` ENUM('pending','fired','cancelled') NOT NULL DEFAULT 'pending'");

        // revert moment về enum not null — cần đảm bảo không có giá trị null trước khi revert
        DB::statement("ALTER TABLE `task_assignment_reminders` CHANGE `moment` `moment` ENUM('before','on','after') NOT NULL");

        Schema::table('task_assignment_reminders', function (Blueprint $table) {
            $table->dateTime('remind_at')->nullable(false)->change();
            $table->foreignId('notification_schedule_id')->nullable(false)->change();
            $table->foreignId('task_assignment_item_id')->nullable(false)->change();
        });

        Schema::table('task_assignment_reminders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_assignment_document_id');
            $table->dropColumn('reminder_type');
        });
    }
};
