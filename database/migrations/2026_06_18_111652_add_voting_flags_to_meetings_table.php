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
        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('is_voting_result_hidden_until_end')->default(false)->after('status');
            $table->boolean('is_vote_change_allowed')->default(false)->after('is_voting_result_hidden_until_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'is_voting_result_hidden_until_end',
                'is_vote_change_allowed'
            ]);
        });
    }
};
