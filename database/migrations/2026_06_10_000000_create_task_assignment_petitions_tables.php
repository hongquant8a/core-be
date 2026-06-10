<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_petitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('task_assignment_departments')->nullOnDelete();
            $table->date('submission_date');
            $table->date('deadline_date')->nullable();
            $table->string('sender_name');
            $table->string('sender_address', 500)->nullable();
            $table->string('sender_cccd', 20)->nullable();
            $table->string('sender_phone', 30)->nullable();
            $table->string('sender_email')->nullable();
            $table->text('content')->nullable();
            $table->string('processing_status', 30)->default('new');
            // Cập nhật tình hình xử lý
            $table->dateTime('completed_at')->nullable();
            $table->string('document_number')->nullable();
            $table->text('document_excerpt')->nullable();
            $table->text('response_content')->nullable();
            // ---
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('department_id');
            $table->index('processing_status');
            $table->index('submission_date');
            $table->index('organization_id');
        });

        Schema::create('task_assignment_petition_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petition_id')->constrained('task_assignment_petitions')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['petition_id', 'media_id'], 'ta_petition_attach_petition_media_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_petition_attachments');
        Schema::dropIfExists('task_assignment_petitions');
    }
};
