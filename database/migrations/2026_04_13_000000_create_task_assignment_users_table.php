<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_assignment_department_id')->constrained('task_assignment_departments')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
            $table->index(['task_assignment_department_id', 'status']);
            $table->index('organization_id');
        });

        // Migrate existing data: chuyển task_assignment_department_id từ users sang bảng mới
        DB::statement("
            INSERT INTO task_assignment_users (user_id, task_assignment_department_id, status, organization_id, created_by, updated_by, created_at, updated_at)
            SELECT u.id, u.task_assignment_department_id, 'active', mhr.organization_id, u.id, u.id, NOW(), NOW()
            FROM users u
            INNER JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\\\Modules\\\\Core\\\\Models\\\\User'
            WHERE u.task_assignment_department_id IS NOT NULL
            GROUP BY u.id, u.task_assignment_department_id, mhr.organization_id
        ");

        // Xóa cột cũ trên bảng users
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_assignment_department_id');
        });
    }

    public function down(): void
    {
        // Khôi phục cột cũ trên bảng users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('task_assignment_department_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('task_assignment_departments')
                ->nullOnDelete();
        });

        // Migrate data back
        DB::statement("
            UPDATE users u
            INNER JOIN task_assignment_users tau ON tau.user_id = u.id
            SET u.task_assignment_department_id = tau.task_assignment_department_id
        ");

        Schema::dropIfExists('task_assignment_users');
    }
};
