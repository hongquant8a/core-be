<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map of FQCN to Alias
     */
    protected array $morphMaps = [
        \App\Modules\Core\Models\User::class => 'user',
        \App\Modules\Core\Models\Setting::class => 'setting',
        \App\Modules\TaskAssignment\Models\TaskAssignmentItem::class => 'task_assignment_item',
        \App\Modules\Meeting\Models\Meeting::class => 'meeting',
        \App\Modules\Scheduling\Models\Schedule::class => 'schedule',
        \App\Modules\Meeting\Models\MeetingDocument::class => 'meeting_document',
        \App\Models\Reminder::class => 'reminder',
    ];

    /**
     * List of tables and their polymorphic column names
     */
    protected array $tables = [
        'media' => 'model_type',
        'model_has_permissions' => 'model_type',
        'model_has_roles' => 'model_type',
        'notifications' => 'notifiable_type',
        'personal_access_tokens' => 'tokenable_type',
        'log_activities' => 'user_type',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table => $column) {
            if (DB::getSchemaBuilder()->hasTable($table) && DB::getSchemaBuilder()->hasColumn($table, $column)) {
                foreach ($this->morphMaps as $fqcn => $alias) {
                    DB::table($table)->where($column, $fqcn)->update([$column => $alias]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table => $column) {
            if (DB::getSchemaBuilder()->hasTable($table) && DB::getSchemaBuilder()->hasColumn($table, $column)) {
                foreach ($this->morphMaps as $fqcn => $alias) {
                    DB::table($table)->where($column, $alias)->update([$column => $fqcn]);
                }
            }
        }
    }
};
