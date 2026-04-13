<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->foreignId('assigned_by')
                ->nullable()
                ->after('completed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('assigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
        });
    }
};
