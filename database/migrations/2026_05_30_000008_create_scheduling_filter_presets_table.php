<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_filter_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('name', 255);
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['organization_id', 'user_id'], 'idx_org_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_filter_presets');
    }
};
