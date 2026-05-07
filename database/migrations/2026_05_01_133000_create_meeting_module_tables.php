<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meeting_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('google_maps_url')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meeting_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_type_id')->nullable()->constrained('meeting_types')->nullOnDelete();
            $table->foreignId('meeting_location_id')->nullable()->constrained('meeting_locations')->nullOnDelete();
            $table->string('title');
            $table->boolean('is_public')->default(false);
            $table->text('content')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->dateTime('published_at')->nullable();
            $table->boolean('attendance_locked')->default(false);
            $table->dateTime('runtime_started_at')->nullable();
            $table->dateTime('runtime_paused_at')->nullable();
            $table->dateTime('runtime_ended_at')->nullable();
            // Highlight pointers cho Tab 8 màn chiếu (current_meeting_agenda_id +
            // current_meeting_discussion_registration_id) được add bởi migration
            // 2026_05_07_200000_add_highlight_pointers_to_meetings — phải tách ra do
            // 2 target table (meeting_agendas, meeting_discussion_registrations) tạo ở
            // các migration sau (140000, 153000), không thể đặt FK trong file này.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'is_public']);
            $table->index(['start_time']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('meeting_document_types');
        Schema::dropIfExists('meeting_locations');
        Schema::dropIfExists('meeting_types');
    }
};
