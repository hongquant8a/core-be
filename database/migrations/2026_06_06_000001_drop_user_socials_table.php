<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_socials');
    }

    public function down(): void
    {
        // Rollback: recreate from original migration
        Schema::create('user_socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_user_id', 191);
            $table->json('provider_data')->nullable();
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }
};
