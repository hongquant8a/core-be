<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->dropColumn([
                'manager_confirmed',
                'manager_confirmed_by',
                'manager_confirmed_at',
                'manager_confirm_note',
                'is_locked',
                'locked_at',
                'locked_by',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->boolean('manager_confirmed')->default(false)->after('report_document_content');
            $table->unsignedBigInteger('manager_confirmed_by')->nullable()->after('manager_confirmed');
            $table->timestamp('manager_confirmed_at')->nullable()->after('manager_confirmed_by');
            $table->text('manager_confirm_note')->nullable()->after('manager_confirmed_at');
            $table->boolean('is_locked')->default(false)->after('manager_confirm_note');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');
        });
    }
};
