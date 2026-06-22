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
        Schema::dropIfExists('task_assignment_reminders');
        Schema::dropIfExists('meeting_reminders');
        Schema::dropIfExists('schedule_reminders');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreating these tables is beyond the scope of a rollback
        // in a production system. Usually, we don't recreate them.
    }
};
