<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_participant_id')->constrained('meeting_participants')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('checkin_method')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'meeting_participant_id']);
            $table->index(['organization_id', 'meeting_id', 'status']);
            $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meeting_vote_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('meeting_agenda_id')->nullable()->constrained('meeting_agendas')->nullOnDelete();
            $table->string('title');
            $table->string('vote_type')->default('agree_disagree_abstain');
            $table->string('ballot_mode')->default('anonymous');
            $table->boolean('show_result_on_projector')->default(false);
            $table->boolean('show_result_on_personal_device')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            // status field đã bỏ — phase derive từ opened_at + closed_at (drop migration 2026_05_07_230000).
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'meeting_id', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meeting_vote_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_vote_topic_id')->constrained('meeting_vote_topics')->cascadeOnDelete();
            $table->foreignId('meeting_participant_id')->constrained('meeting_participants')->cascadeOnDelete();
            $table->string('option');
            $table->dateTime('voted_at');
            $table->timestamps();

            $table->unique(['meeting_vote_topic_id', 'meeting_participant_id'], 'meeting_vote_resp_topic_part_unique');
            $table->index(['organization_id', 'meeting_vote_topic_id'], 'meeting_vote_resp_org_topic_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_vote_responses');
        Schema::dropIfExists('meeting_vote_topics');
        Schema::dropIfExists('meeting_attendances');
    }
};
