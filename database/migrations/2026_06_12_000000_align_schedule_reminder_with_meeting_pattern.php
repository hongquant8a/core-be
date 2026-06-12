<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bước 1: Cho phép NULL trước (vẫn giữ nguyên enum values hiện tại)
        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN moment ENUM('immediate','before','on','after') NULL DEFAULT 'before'");

        // Bước 2: Chuyển các bản ghi immediate sang NULL
        DB::table('schedule_reminders')
            ->where('moment', 'immediate')
            ->update(['moment' => null]);

        // Bước 3: Bỏ 'immediate' khỏi enum (giờ chỉ còn before/on/after)
        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN moment ENUM('before','on','after') NULL DEFAULT 'before'");

        // Bước 4: Thêm 'active' vào enum status (cần cho instant reminder)
        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN status ENUM('active','pending','fired','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Bước 1: Thêm 'immediate' trở lại enum
        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN moment ENUM('immediate','before','on','after') NULL DEFAULT 'before'");

        // Bước 2: Chuyển NULL về 'immediate'
        DB::table('schedule_reminders')
            ->whereNull('moment')
            ->update(['moment' => 'immediate']);

        // Bước 3: Đưa về NOT NULL
        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN moment ENUM('immediate','before','on','after') NOT NULL DEFAULT 'before'");

        // Bước 4: Bỏ 'active' khỏi enum status
        DB::table('schedule_reminders')->where('status', 'active')->update(['status' => 'pending']);
        DB::statement("ALTER TABLE schedule_reminders MODIFY COLUMN status ENUM('pending','fired','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
