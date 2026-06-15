<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_reminders', function (Blueprint $table) {
            if (! Schema::hasColumn('schedule_reminders', 'reminder_type')) {
                $table->string('reminder_type')->default('scheduled')->after('schedule_id');
            }
        });

        // moment IS NULL → instant, còn lại → scheduled
        DB::table('schedule_reminders')->whereNull('moment')->update(['reminder_type' => 'instant']);
    }

    public function down(): void
    {
        Schema::table('schedule_reminders', function (Blueprint $table) {
            if (Schema::hasColumn('schedule_reminders', 'reminder_type')) {
                $table->dropColumn('reminder_type');
            }
        });
    }
};
