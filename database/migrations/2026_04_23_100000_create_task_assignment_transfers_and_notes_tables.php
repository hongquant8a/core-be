<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_item_user_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id');
            $table->foreign('task_assignment_item_id', 'fk_ta_transfers_item')->references('id')->on('task_assignment_items')->cascadeOnDelete();
            
            $table->foreignId('from_user_id')->nullable();
            $table->foreign('from_user_id', 'fk_ta_transfers_from_user')->references('id')->on('users')->nullOnDelete();
            
            $table->foreignId('to_user_id')->nullable();
            $table->foreign('to_user_id', 'fk_ta_transfers_to_user')->references('id')->on('users')->nullOnDelete();
            
            $table->foreignId('transferred_by_user_id')->nullable();
            $table->foreign('transferred_by_user_id', 'fk_ta_transfers_by_user')->references('id')->on('users')->nullOnDelete();
            
            $table->foreignId('transferred_department_id')->nullable();
            $table->foreign('transferred_department_id', 'fk_ta_transfers_dept')->references('id')->on('task_assignment_departments')->nullOnDelete();
            
            $table->dateTime('transferred_at');
            $table->text('note')->nullable();
            
            $table->foreignId('organization_id')->nullable();
            $table->foreign('organization_id', 'fk_ta_transfers_org')->references('id')->on('organizations')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_assignment_item_id', 'transferred_at'], 'ta_item_transfers_item_id_transferred_at_index');
        });

        Schema::create('task_assignment_item_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id');
            $table->foreign('task_assignment_item_id', 'fk_ta_notes_item')->references('id')->on('task_assignment_items')->cascadeOnDelete();
            
            $table->foreignId('author_user_id')->nullable();
            $table->foreign('author_user_id', 'fk_ta_notes_author')->references('id')->on('users')->nullOnDelete();
            
            $table->string('author_role', 20); // 'manager' | 'assignee'
            $table->text('content');
            
            $table->foreignId('organization_id')->nullable();
            $table->foreign('organization_id', 'fk_ta_notes_org')->references('id')->on('organizations')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_assignment_item_id', 'created_at'], 'ta_item_notes_item_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_item_notes');
        Schema::dropIfExists('task_assignment_item_user_transfers');
    }
};
