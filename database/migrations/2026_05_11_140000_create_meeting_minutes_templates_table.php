<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Template per module Meeting — KHÔNG scope theo organization, tất cả org dùng chung.
        Schema::create('meeting_minutes_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active'); // active | inactive
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes_templates');
    }
};
