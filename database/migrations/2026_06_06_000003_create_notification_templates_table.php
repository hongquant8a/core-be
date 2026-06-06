<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('module_key');
            $table->string('event_key')->nullable()->comment('để tham khảo, không dùng trong unique');
            $table->string('channel')->default('zalo_zns');
            $table->string('template_id');
            $table->json('variable_mapping')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'module_key', 'channel'], 'uqx_notification_templates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
