<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_reminders', function (Blueprint $table) {
            $table->unsignedInteger('offset_minutes')->default(0)->after('moment');
            $table->json('channels')->nullable()->after('offset_minutes');
            $table->string('source')->default('PRESET')->after('channels');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_reminders', function (Blueprint $table) {
            $table->dropColumn(['offset_minutes', 'channels', 'source']);
        });
    }
};
