<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('media_id');
            $table->string('file_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();

            $table->index(['organization_id', 'schedule_id', 'sort_order'], 'idx_org_schedule_attachments_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_attachments');
    }
};
