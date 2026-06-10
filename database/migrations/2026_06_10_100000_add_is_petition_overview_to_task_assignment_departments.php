<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_departments', function (Blueprint $table) {
            $table->boolean('is_petition_overview')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_departments', function (Blueprint $table) {
            $table->dropColumn('is_petition_overview');
        });
    }
};
