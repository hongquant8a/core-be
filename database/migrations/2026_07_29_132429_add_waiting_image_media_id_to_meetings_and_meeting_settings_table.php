<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedBigInteger('waiting_image_media_id')->nullable()->after('projector_image_media_id');
        });

        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('waiting_image_media_id')->nullable()->after('projector_image_media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->dropColumn('waiting_image_media_id');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('waiting_image_media_id');
        });
    }
};
