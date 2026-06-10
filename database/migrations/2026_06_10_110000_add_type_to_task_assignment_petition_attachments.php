<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_petition_attachments', function (Blueprint $table) {
            $table->string('type', 20)->default('petition')->after('petition_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_petition_attachments', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
