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
        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->boolean('allow_host_management')->default(true)->after('qr_icon_media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->dropColumn('allow_host_management');
        });
    }
};
