<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable(); // null = system wide
            $table->string('moment')->default('before'); // before, on, after
            $table->integer('offset_minutes')->default(0);
            $table->string('label');
            $table->json('channels'); // ['fcm', 'zalo', 'sms', 'inapp']
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id'], 'idx_preset_org');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_presets');
    }
};
