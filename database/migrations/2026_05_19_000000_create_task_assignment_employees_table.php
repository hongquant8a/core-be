<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['user_id', 'organization_id'], 'ta_employees_user_org_unique');
            $table->index('organization_id');
            $table->index('status');
        });

        // Backfill: mọi user đang là thành viên dept (task_assignment_users) → tự động thành nhân viên active.
        // DISTINCT (user_id, organization_id) tránh trùng do user thuộc nhiều dept.
        DB::statement("
            INSERT INTO task_assignment_employees (user_id, organization_id, status, created_at, updated_at)
            SELECT DISTINCT user_id, organization_id, 'active', NOW(), NOW()
            FROM task_assignment_users
            WHERE user_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_employees');
    }
};
