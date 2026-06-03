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
            $table->dropColumn(['executive_requires_approval', 'office_requires_approval']);
            $table->boolean('requires_approval')->default(false)->after('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
            $table->boolean('executive_requires_approval')->default(false);
            $table->boolean('office_requires_approval')->default(false);
        });
    }
};
