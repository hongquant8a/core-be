<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->boolean('executive_requires_approval')->default(false)->after('organization_id');
            $table->boolean('office_requires_approval')->default(false)->after('executive_requires_approval');
        });

        DB::table('org_scheduling_settings')->update([
            'executive_requires_approval' => DB::raw('requires_approval'),
            'office_requires_approval'    => DB::raw('requires_approval'),
        ]);

        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });

        Schema::table('scheduling_settings', function (Blueprint $table) {
            $table->dropColumn(['approval_enabled', 'approval_module_types']);
        });
    }

    public function down(): void
    {
        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('organization_id');
        });

        DB::table('org_scheduling_settings')->update([
            'requires_approval' => DB::raw('executive_requires_approval OR office_requires_approval'),
        ]);

        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->dropColumn(['executive_requires_approval', 'office_requires_approval']);
        });

        Schema::table('scheduling_settings', function (Blueprint $table) {
            $table->boolean('approval_enabled')->default(false)->after('organization_id');
            $table->json('approval_module_types')->nullable()->after('approval_enabled');
        });
    }
};
