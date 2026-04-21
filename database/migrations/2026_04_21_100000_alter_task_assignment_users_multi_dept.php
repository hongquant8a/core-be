<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('task_assignment_users', 'is_primary')) {
            Schema::table('task_assignment_users', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false)->after('task_assignment_department_id');
            });
        }

        // The old unique index is used by the user_id FK; drop FK first, swap index, restore FK.
        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropForeign('task_assignment_users_user_id_foreign');
            $table->dropUnique('task_assignment_users_user_id_organization_id_unique');
            // Keep a plain index on user_id so the FK can be recreated.
            $table->index('user_id', 'task_assignment_users_user_id_index');
            $table->unique(
                ['user_id', 'task_assignment_department_id', 'organization_id'],
                'ta_users_user_dept_org_unique'
            );
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropForeign('task_assignment_users_user_id_foreign');
            $table->dropUnique('ta_users_user_dept_org_unique');
            $table->dropIndex('task_assignment_users_user_id_index');
            $table->unique(['user_id', 'organization_id'], 'task_assignment_users_user_id_organization_id_unique');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
