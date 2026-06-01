<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->boolean('approval_enabled')->default(false);
            $table->json('approval_module_types')->nullable();
            $table->json('default_channels')->nullable();
            $table->json('working_sessions')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_settings');
    }
};
