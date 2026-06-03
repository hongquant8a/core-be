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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE schedule_notifications MODIFY COLUMN channel ENUM('FCM', 'ZALO', 'ZALO_ZNS', 'SMS', 'APP', 'MAIL') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE schedule_notifications MODIFY COLUMN channel ENUM('FCM', 'ZALO', 'SMS', 'APP', 'MAIL') NOT NULL");
    }
};
