<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_employees', function (Blueprint $table) {
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

            $table->unique(['user_id', 'organization_id'], 'sch_employees_user_org_unique');
            $table->index('organization_id');
            $table->index('status');
        });

        // Backfill: mọi user có trong hệ thống sẽ tự động là active employee của organization ID = 1
        DB::statement("
            INSERT INTO scheduling_employees (user_id, organization_id, status, created_at, updated_at)
            SELECT id, 1, 'active', NOW(), NOW()
            FROM users
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_employees');
    }
};
