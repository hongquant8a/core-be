<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Artisan::call('permissions:sync-task-module');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for permission sync rollback
    }
};
