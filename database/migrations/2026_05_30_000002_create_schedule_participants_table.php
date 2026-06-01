<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('display_name', 255)->nullable();
            $table->string('position_name', 255)->nullable();
            $table->boolean('is_external')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index('schedule_id', 'idx_schedule');
            $table->index('user_id', 'idx_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_participants');
    }
};
