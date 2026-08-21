<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Gộp permission dư thừa của module Quản lý công việc và sắp lại cây theo nav.
     * Command tự snapshot quyền cũ của từng vai trò trước khi seeder xóa nên chạy được trên server đã có dữ liệu.
     */
    public function up(): void
    {
        Artisan::call('permissions:migrate-task-tree');
    }

    public function down(): void
    {
        // Không rollback: quyền cũ đã được gộp vào quyền mới.
    }
};
