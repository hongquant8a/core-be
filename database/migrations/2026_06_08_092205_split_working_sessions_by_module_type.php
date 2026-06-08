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
            $table->json('executive_working_sessions')->nullable()->after('office_requires_approval');
            $table->json('office_working_sessions')->nullable()->after('executive_working_sessions');
        });

        // Copy working_sessions từ scheduling_settings → org_scheduling_settings
        $legacySettings = DB::table('scheduling_settings')->get();
        foreach ($legacySettings as $setting) {
            $sessions = $setting->working_sessions;
            DB::table('org_scheduling_settings')
                ->where('organization_id', $setting->organization_id)
                ->update([
                    'executive_working_sessions' => $sessions,
                    'office_working_sessions'    => $sessions,
                ]);
        }

        Schema::table('scheduling_settings', function (Blueprint $table) {
            $table->dropColumn('working_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('scheduling_settings', function (Blueprint $table) {
            $table->json('working_sessions')->nullable()->after('default_channels');
        });

        $orgSettings = DB::table('org_scheduling_settings')->get();
        foreach ($orgSettings as $os) {
            DB::table('scheduling_settings')
                ->where('organization_id', $os->organization_id)
                ->update([
                    'working_sessions' => $os->executive_working_sessions,
                ]);
        }

        Schema::table('org_scheduling_settings', function (Blueprint $table) {
            $table->dropColumn(['executive_working_sessions', 'office_working_sessions']);
        });
    }
};
