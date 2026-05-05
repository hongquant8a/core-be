<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Giấy mời theo từng đại biểu — tạo khi publish meeting.
        // Recipient = participant (đại biểu mời) HOẶC attendee trực tiếp (chủ trì + thư ký, không cần qua participants).
        // Constraint: 1 trong 2 phải có giá trị (enforce ở app layer, MySQL không hỗ trợ CHECK trước 8.0.16).
        Schema::create('meeting_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_participant_id')->nullable()->constrained('meeting_participants')->cascadeOnDelete();
            $table->foreignId('meeting_attendee_id')->nullable()->constrained('meeting_attendees')->cascadeOnDelete();
            $table->string('send_type')->default('now'); // now | scheduled
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'status'], 'meeting_invit_org_mtg_status_idx');
        });

        // Nhắc lịch họp — manual hoặc scheduled.
        Schema::create('meeting_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('reminder_type')->default('manual'); // manual | scheduled
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'status'], 'meeting_remind_org_mtg_status_idx');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // Log lượt xem cuộc họp + tài liệu.
        Schema::create('meeting_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_document_id')->nullable()->constrained('meeting_documents')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('viewed_at');

            $table->index(['meeting_id', 'viewed_at'], 'meeting_views_mtg_viewed_idx');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_views');
        Schema::dropIfExists('meeting_reminders');
        Schema::dropIfExists('meeting_invitations');
    }
};
