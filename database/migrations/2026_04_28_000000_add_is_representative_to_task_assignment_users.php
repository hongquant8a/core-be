<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->boolean('is_representative')->default(false)->after('is_primary');
            $table->index(['task_assignment_department_id', 'is_representative'], 'ta_users_dept_rep_index');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropIndex('ta_users_dept_rep_index');
            $table->dropColumn('is_representative');
        });
    }
};
