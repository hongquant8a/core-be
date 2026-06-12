<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Đồng bộ pattern với Meeting/TaskAssignment: instant → moment=null
        // Phải update data trước rồi mới MODIFY COLUMN, nếu không MySQL báo lỗi "Data truncated for column 'moment'"
        DB::table('schedule_reminders')
            ->where('moment', 'immediate')
            ->update(['moment' => null]);

        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN moment ENUM('before','on','after') NULL DEFAULT 'before'");
    }

    public function down(): void
    {
        DB::table('schedule_reminders')
            ->whereNull('moment')
            ->update(['moment' => 'immediate']);

        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN moment ENUM('immediate','before','on','after') NOT NULL DEFAULT 'before'");
    }
};
