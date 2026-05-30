<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('notification_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['group_id', 'user_id'], 'uq_group_user_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_group_members');
    }
};
