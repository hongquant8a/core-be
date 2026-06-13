<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_discussion_registrations', 'answer_attachment_id')) {
                $table->unsignedBigInteger('answer_attachment_id')->nullable()->after('answer_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_discussion_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_discussion_registrations', 'answer_attachment_id')) {
                $table->dropColumn('answer_attachment_id');
            }
        });
    }
};
