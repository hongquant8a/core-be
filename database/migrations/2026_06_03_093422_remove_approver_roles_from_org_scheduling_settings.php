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
        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->dropColumn(['executive_approver_roles', 'office_approver_roles']);
        });
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
