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
        if (!Schema::hasTable('org_scheduling_settings')) {
            Schema::create('org_scheduling_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->unique();
                $table->boolean('requires_approval')->default(false);
                $table->timestamps();
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            });
            return;
        }

        try {
            Schema::table('org_scheduling_settings', function (Blueprint $table) {
                $table->dropColumn(['executive_approver_roles', 'office_approver_roles']);
            });
        } catch (\Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->text('executive_approver_roles')->nullable();
            $table->text('office_approver_roles')->nullable();
        });
    }
};
