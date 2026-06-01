<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_employee_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scheduling_employee_group_id');
            $table->unsignedBigInteger('scheduling_employee_id');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('scheduling_employee_group_id', 'fk_segm_group_id')
                ->references('id')->on('scheduling_employee_groups')->cascadeOnDelete();
            $table->foreign('scheduling_employee_id', 'fk_segm_employee_id')
                ->references('id')->on('scheduling_employees')->cascadeOnDelete();

            $table->unique(['scheduling_employee_group_id', 'scheduling_employee_id'], 'uq_group_employee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_employee_group_members');
    }
};
