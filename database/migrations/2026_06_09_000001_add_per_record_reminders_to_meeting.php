<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_reminders', function (Blueprint $table) {
            $table->unsignedInteger('offset_minutes')->default(0)->after('moment');
            $table->json('channels')->nullable()->after('offset_minutes');
            $table->string('source')->default('PRESET')->after('channels');
            $table->dateTime('remind_at')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_reminders', function (Blueprint $table) {
            $table->dropColumn(['offset_minutes', 'channels', 'source', 'remind_at']);
        });
    }
};
