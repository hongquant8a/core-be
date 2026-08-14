<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * chat_conversations — engine chat dùng chung cho 2 loại:
 *  - type=meeting_group: 1 conversation / 1 meeting (chat nhóm nội bộ, gate bởi meetings.internal_chat_enabled).
 *  - type=direct: 1 conversation / 1 cặp user (DM toàn hệ thống, không gắn meeting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type')->default('direct');
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->cascadeOnDelete();
            $table->unsignedBigInteger('user_one_id')->nullable();
            $table->unsignedBigInteger('user_two_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('meeting_id');
            $table->unique(['organization_id', 'user_one_id', 'user_two_id'], 'chat_conv_org_pair_unique');
            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
