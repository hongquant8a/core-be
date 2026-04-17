<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            // Polymorphic owner — null = global default, set = override cho 1 entity
            $table->string('configurable_type')->nullable();
            $table->unsignedBigInteger('configurable_id')->nullable();
            $table->index(['configurable_type', 'configurable_id'], 'notif_sched_morph_idx');
            $table->enum('moment', ['before', 'on', 'after']);
            $table->unsignedInteger('offset_minutes')->nullable();
            $table->json('channels')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['moment', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
    }
};
