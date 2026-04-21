<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->boolean('manager_confirmed')->default(false)->after('report_document_content');
            $table->unsignedBigInteger('manager_confirmed_by')->nullable()->after('manager_confirmed');
            $table->dateTime('manager_confirmed_at')->nullable()->after('manager_confirmed_by');
            $table->text('manager_confirm_note')->nullable()->after('manager_confirmed_at');
            $table->boolean('is_locked')->default(false)->after('manager_confirm_note');
            $table->dateTime('locked_at')->nullable()->after('is_locked');
            $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');

            $table->foreign('manager_confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['manager_confirmed', 'is_locked'], 'ta_item_reports_confirmed_locked_index');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->dropIndex('ta_item_reports_confirmed_locked_index');
            $table->dropForeign(['manager_confirmed_by']);
            $table->dropForeign(['locked_by']);
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
};
