<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_discussion_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_agenda_id')->nullable()->constrained('meeting_agendas')->nullOnDelete();
            $table->foreignId('meeting_participant_id')->constrained('meeting_participants')->cascadeOnDelete();
            $table->string('type')->default('discussion');
            $table->text('content');
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status')->default('registered');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'type', 'status'], 'meeting_disc_reg_org_mtg_type_status_idx');
            $table->index(['meeting_id', 'meeting_agenda_id', 'sort_order'], 'meeting_disc_reg_mtg_agenda_sort_idx');
        });

        Schema::create('meeting_personal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_participant_id')->constrained('meeting_participants')->cascadeOnDelete();
            $table->longText('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'meeting_participant_id'], 'meeting_personal_notes_org_mtg_part_idx');
        });

        Schema::create('meeting_personal_note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_personal_note_id')->constrained(table: 'meeting_personal_notes', indexName: 'meeting_pna_note_id_fk')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'meeting_personal_note_id', 'sort_order'], 'meeting_pn_attach_org_note_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_personal_note_attachments');
        Schema::dropIfExists('meeting_personal_notes');
        Schema::dropIfExists('meeting_discussion_registrations');
    }
};
