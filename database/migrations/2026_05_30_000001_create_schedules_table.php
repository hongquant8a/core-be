<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('module_type'); // EXECUTIVE or OFFICE
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->char('session', 1); // S, C, T
            $table->text('content');
            $table->unsignedBigInteger('host_id');
            $table->tinyInteger('host_priority_weight')->unsigned()->default(0);
            $table->string('location')->nullable();
            $table->string('preparation_unit')->nullable();
            $table->string('participant_count')->nullable();
            $table->string('nature'); // HOST or ATTEND
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('color_code', 7)->default('#FFFFFF');
            $table->text('participants_text')->nullable();
            $table->text('departments_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('status')->default(0); // 0=DRAFT, 1=PENDING, 2=PUBLISHED, 3=CANCELLED
            $table->tinyInteger('week_number');
            $table->smallInteger('year');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('host_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['organization_id', 'module_type', 'event_date', 'session', 'status'], 'idx_org_module_date_session_status');
            $table->index(['organization_id', 'year', 'week_number'], 'idx_org_week_number');
            $table->index(['organization_id', 'host_id', 'event_date'], 'idx_org_host_date');
            $table->index(['organization_id', 'driver_id', 'event_date'], 'idx_org_driver_date');
            $table->index(['organization_id', 'event_date', 'session', 'sort_order'], 'idx_org_sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
