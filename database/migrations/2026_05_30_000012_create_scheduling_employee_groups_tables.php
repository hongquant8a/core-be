<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('scheduling_employee_groups')) {
            Schema::create('scheduling_employee_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('active'); // active, inactive
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('organization_id');
            });
        }

        if (!Schema::hasTable('scheduling_employee_group_members')) {
            Schema::create('scheduling_employee_group_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                
                // Define constraint names manually to prevent key length exceed (max 64 characters)
                $table->foreignId('scheduling_employee_group_id')
                    ->constrained('scheduling_employee_groups', 'id', 'seg_members_group_fk')
                    ->cascadeOnDelete();
                    
                $table->foreignId('scheduling_employee_id')
                    ->constrained('scheduling_employees', 'id', 'seg_members_employee_fk')
                    ->cascadeOnDelete();
                    
                $table->timestamps();

                $table->unique(
                    ['scheduling_employee_group_id', 'scheduling_employee_id'],
                    'seg_members_unique'
                );
                $table->index('organization_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_employee_group_members');
        Schema::dropIfExists('scheduling_employee_groups');
    }
};
