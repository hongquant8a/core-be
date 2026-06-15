<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('completion_percent')->nullable()->after('completed_at');
            $table->unsignedBigInteger('created_by')->nullable()->after('organization_id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['completion_percent', 'created_by', 'updated_by']);
        });
    }
};
