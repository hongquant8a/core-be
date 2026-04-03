<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_departments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::create('task_assignment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::create('task_assignment_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::create('task_assignment_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->date('issue_date')->nullable();
            $table->foreignId('task_assignment_type_id')->nullable()->constrained('task_assignment_types')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('issue_date');
            $table->index('task_assignment_type_id');
            $table->index('organization_id');
        });

        Schema::create('task_assignment_document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_document_id')->constrained('task_assignment_documents', indexName: 'ta_doc_attach_doc_id_foreign')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['task_assignment_document_id', 'media_id'], 'ta_doc_attach_doc_id_media_id_unique');
            $table->index(['task_assignment_document_id', 'sort_order'], 'ta_doc_attach_doc_id_sort_order_index');
        });

        Schema::create('task_assignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_document_id')->constrained('task_assignment_documents')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('task_assignment_item_type_id')->nullable()->constrained('task_assignment_item_types')->nullOnDelete();
            $table->string('deadline_type')->default('no_deadline');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->string('processing_status')->default('todo');
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->string('priority')->default('medium');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('task_assignment_document_id');
            $table->index('processing_status');
            $table->index(['deadline_type', 'end_at']);
            $table->index('task_assignment_item_type_id');
            $table->index('priority');
            $table->index('organization_id');
        });

        Schema::create('task_assignment_item_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id')->constrained('task_assignment_items')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('task_assignment_departments')->cascadeOnDelete();
            $table->string('role')->default('main');
            $table->timestamps();

            $table->unique(['task_assignment_item_id', 'department_id'], 'ta_item_dept_item_id_dept_id_unique');
        });

        Schema::create('task_assignment_item_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id')->constrained('task_assignment_items')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('task_assignment_departments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('assignment_role')->default('main');
            $table->string('assignment_status')->default('assigned');
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['task_assignment_item_id', 'user_id']);
            $table->index(['department_id', 'assignment_status']);
        });

        Schema::create('task_assignment_item_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id')->constrained('task_assignment_items')->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->string('report_document_number')->nullable();
            $table->text('report_document_excerpt')->nullable();
            $table->text('report_document_content')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_assignment_item_id', 'reporter_user_id'], 'ta_item_reports_item_id_reporter_id_index');
            $table->index('completed_at');
            $table->index('organization_id');
        });

        Schema::create('task_assignment_item_report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_report_id')->constrained('task_assignment_item_reports', indexName: 'ta_item_report_attach_report_id_foreign')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['task_assignment_item_report_id', 'media_id'], 'ta_item_report_attach_report_id_media_id_unique');
        });

        // Thêm field task_assignment_department_id vào users (1 user thuộc 1 phòng ban)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('task_assignment_department_id')
                ->nullable()
                ->after('status')
                ->constrained('task_assignment_departments')
                ->nullOnDelete();
        });

        Schema::create('task_assignment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id')->constrained('task_assignment_items')->cascadeOnDelete();
            $table->dateTime('remind_at');
            $table->dateTime('sent_at')->nullable();
            $table->string('channel');
            $table->foreignId('recipient_department_id')->nullable()->constrained('task_assignment_departments')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_assignment_department_id');
        });
        Schema::dropIfExists('task_assignment_reminders');
        Schema::dropIfExists('task_assignment_item_report_attachments');
        Schema::dropIfExists('task_assignment_item_reports');
        Schema::dropIfExists('task_assignment_item_user');
        Schema::dropIfExists('task_assignment_item_department');
        Schema::dropIfExists('task_assignment_items');
        Schema::dropIfExists('task_assignment_document_attachments');
        Schema::dropIfExists('task_assignment_documents');
        Schema::dropIfExists('task_assignment_item_types');
        Schema::dropIfExists('task_assignment_types');
        Schema::dropIfExists('task_assignment_departments');
    }
};
