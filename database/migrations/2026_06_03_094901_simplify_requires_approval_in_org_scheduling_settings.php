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
            return;
        }

        // Drop columns chỉ khi chúng tồn tại
        $columns = Schema::getColumnListing('org_scheduling_settings');
        if (in_array('executive_requires_approval', $columns) || in_array('office_requires_approval', $columns)) {
            Schema::table('org_scheduling_settings', function (Blueprint $table) {
                $cols = array_intersect(['executive_requires_approval', 'office_requires_approval'], Schema::getColumnListing('org_scheduling_settings'));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (!in_array('requires_approval', $columns)) {
            Schema::table('org_scheduling_settings', function (Blueprint $table) {
                $table->boolean('requires_approval')->default(false)->after('organization_id');
            });
        }
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
