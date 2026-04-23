<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize any existing 'reported' task status to 'in_progress'
        DB::table('task_assignment_items')
            ->where('processing_status', 'reported')
            ->update(['processing_status' => 'in_progress']);

        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['confirmed_by', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
