<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_configs', function (Blueprint $table) {
            $table->id();
            // Polymorphic owner — null = global default, set = override cho 1 entity (vd 1 document giao việc)
            $table->string('configurable_type')->nullable();
            $table->unsignedBigInteger('configurable_id')->nullable();
            $table->index(['configurable_type', 'configurable_id'], 'notif_event_cfg_morph_idx');
            $table->string('event_key', 50);
            $table->boolean('enabled')->default(false);
            $table->json('channels')->nullable();
            $table->timestamps();

            // Mỗi (owner, event_key) chỉ có 1 config duy nhất
            $table->unique(['configurable_type', 'configurable_id', 'event_key'], 'notif_event_cfg_owner_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_configs');
    }
};
