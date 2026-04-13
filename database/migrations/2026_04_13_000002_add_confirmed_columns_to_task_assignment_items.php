<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->foreignId('confirmed_by')
                ->nullable()
                ->after('assigned_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('confirmed_at')
                ->nullable()
                ->after('confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
        });
    }
};
