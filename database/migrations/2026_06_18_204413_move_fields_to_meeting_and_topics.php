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
        // 1. Add fields
        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('allow_host_management')->default(true)->after('is_public');
        });

        Schema::table('meeting_vote_topics', function (Blueprint $table) {
            $table->boolean('is_voting_result_hidden_until_end')->default(false)->after('title');
            $table->boolean('is_vote_change_allowed')->default(false)->after('is_voting_result_hidden_until_end');
            $table->boolean('calculate_by_total_delegates')->default(true)->after('is_vote_change_allowed');
            $table->boolean('calculate_by_present_delegates')->default(true)->after('calculate_by_total_delegates');
        });

        // Migrate data
        \Illuminate\Support\Facades\DB::table('meetings')->orderBy('id')->chunk(100, function ($meetings) {
            foreach ($meetings as $meeting) {
                // Get setting for allow_host_management
                $setting = \Illuminate\Support\Facades\DB::table('meeting_settings')
                    ->where('organization_id', $meeting->organization_id)
                    ->first();
                $allowHostManagement = $setting ? (bool) $setting->allow_host_management : true;

                \Illuminate\Support\Facades\DB::table('meetings')
                    ->where('id', $meeting->id)
                    ->update([
                        'allow_host_management' => $allowHostManagement,
                    ]);

                // Migrate vote topic settings
                \Illuminate\Support\Facades\DB::table('meeting_vote_topics')
                    ->where('meeting_id', $meeting->id)
                    ->update([
                        'is_voting_result_hidden_until_end' => $meeting->is_voting_result_hidden_until_end,
                        'is_vote_change_allowed' => $meeting->is_vote_change_allowed,
                    ]);
            }
        });

        // 2. Drop fields
        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->dropColumn('allow_host_management');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('is_voting_result_hidden_until_end');
            $table->dropColumn('is_vote_change_allowed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->boolean('allow_host_management')->default(true)->after('qr_icon_media_id');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('is_voting_result_hidden_until_end')->default(false);
            $table->boolean('is_vote_change_allowed')->default(false);
        });

        // We can't really restore data for setting and old topics fully accurately from here without complex logic,
        // but schema rollback is enough.
        
        Schema::table('meeting_vote_topics', function (Blueprint $table) {
            $table->dropColumn([
                'is_voting_result_hidden_until_end',
                'is_vote_change_allowed',
                'calculate_by_total_delegates',
                'calculate_by_present_delegates',
            ]);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('allow_host_management');
        });
    }
};
