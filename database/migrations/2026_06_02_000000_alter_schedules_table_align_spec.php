<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['parent_schedule_id']);
            
            // Drop redundant fields
            $table->dropColumn(['is_recurring', 'recurrence_rule', 'parent_schedule_id']);

            // Rename preparation_location to preparation_unit
            $table->renameColumn('preparation_location', 'preparation_unit');

            // Add missing fields to align with specs and frontend
            $table->enum('nature', ['HOST', 'ATTEND'])->default('HOST')->after('location');
            $table->string('color_code', 7)->nullable()->after('nature');
            $table->text('participants_text')->nullable()->after('color_code');
            $table->text('departments_text')->nullable()->after('participants_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Re-add redundant fields
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_rule')->nullable();
            $table->unsignedBigInteger('parent_schedule_id')->nullable();

            // Re-add foreign key constraint
            $table->foreign('parent_schedule_id')->references('id')->on('schedules')->nullOnDelete();

            // Rename preparation_unit back to preparation_location
            $table->renameColumn('preparation_unit', 'preparation_location');

            // Drop missing fields
            $table->dropColumn(['nature', 'color_code', 'participants_text', 'departments_text']);
        });
    }
};
