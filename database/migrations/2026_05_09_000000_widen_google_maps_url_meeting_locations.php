<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng `meeting_locations.google_maps_url` từ VARCHAR(255) → VARCHAR(2048).
 * Lý do: full Google Maps place URL với metadata (data=!3m1!...!4m6!...) thường 300-800 chars,
 * embed iframe URL có thể 500-2000 chars. 255 không đủ cho real-world paste.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_locations')) {
            return;
        }

        Schema::table('meeting_locations', function (Blueprint $table) {
            $table->string('google_maps_url', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_locations')) {
            return;
        }

        Schema::table('meeting_locations', function (Blueprint $table) {
            $table->string('google_maps_url', 255)->nullable()->change();
        });
    }
};
