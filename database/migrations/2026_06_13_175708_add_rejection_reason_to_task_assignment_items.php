<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('completion_percent');
            $table->dateTime('reported_at')->nullable()->after('rejection_reason');
            $table->unsignedBigInteger('reported_by')->nullable()->after('reported_at');
            $table->unsignedBigInteger('approved_by')->nullable()->after('completed_at');

            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_items', function (Blueprint $table) {
            $table->dropForeign(['reported_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['rejection_reason', 'reported_at', 'reported_by', 'approved_by']);
        });
    }
};
