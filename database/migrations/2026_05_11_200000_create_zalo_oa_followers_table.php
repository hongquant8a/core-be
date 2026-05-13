<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache local list followers Zalo OA — sync mỗi 45 phút từ
 *   GET https://openapi.zalo.me/v2.0/oa/getfollowers
 *   GET https://openapi.zalo.me/v2.0/oa/getprofile?data={"user_id":...}
 *
 * Strategy: snapshot full list mỗi lần sync. Upsert theo user_id (Zalo), delete row
 * không còn trong response (đã unfollow).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zalo_oa_followers', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64)->unique();           // Zalo user_id (string)
            $table->string('display_name', 255)->nullable();
            $table->string('user_alias', 100)->nullable();
            $table->string('avatar', 1024)->nullable();
            $table->json('raw_profile')->nullable();           // full response getprofile
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('display_name');
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zalo_oa_followers');
    }
};
