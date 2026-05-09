<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendee_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
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

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Trước: meeting_attendee_group_id (FK đơn 1-N). Refactor sang many-to-many
            // qua pivot meeting_attendee_group_members (migration 2026_05_08_120000).
            $table->unsignedBigInteger('user_id');
            $table->string('position_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('status')->default('active');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id'], 'meeting_attendees_org_user_unique');
            $table->index(['organization_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Bổ sung FK chairperson/operator vào meetings (chỉ thêm được sau khi meeting_attendees tồn tại).
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('chairperson_meeting_attendee_id')
                ->nullable()
                ->after('meeting_location_id')
                ->constrained(table: 'meeting_attendees', indexName: 'meetings_chairperson_attendee_fk')
                ->nullOnDelete();
            $table->foreignId('operator_meeting_attendee_id')
                ->nullable()
                ->after('chairperson_meeting_attendee_id')
                ->constrained(table: 'meeting_attendees', indexName: 'meetings_operator_attendee_fk')
                ->nullOnDelete();
        });

        Schema::create('meeting_agendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('content');
            $table->string('person_in_charge')->nullable();
            $table->boolean('allow_discussion_registration')->default(false);
            $table->boolean('allow_question_registration')->default(false);
            $table->foreignId('parent_id')->nullable()->constrained('meeting_agendas')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'parent_id', 'sort_order'], 'meeting_agendas_org_mtg_parent_sort_idx');
        });

        Schema::create('meeting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_agenda_id')->nullable()->constrained('meeting_agendas')->nullOnDelete();
            $table->foreignId('meeting_document_type_id')->nullable()->constrained('meeting_document_types')->nullOnDelete();
            $table->string('title');
            $table->string('document_number')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'sort_order'], 'meeting_documents_org_mtg_sort_idx');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_attendee_id')->constrained('meeting_attendees')->cascadeOnDelete();
            $table->string('display_name');
            $table->string('position_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('response_status')->default('pending');
            $table->text('absence_reason')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'meeting_attendee_id'], 'meeting_participants_mtg_attendee_unique');
            $table->index(['organization_id', 'meeting_id', 'response_status'], 'meeting_participants_org_mtg_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meeting_documents');
        Schema::dropIfExists('meeting_agendas');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meeting_attendee_groups');
    }
};
