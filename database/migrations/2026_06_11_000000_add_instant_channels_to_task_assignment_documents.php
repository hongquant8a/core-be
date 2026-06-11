<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_documents', function (Blueprint $table) {
            $table->json('instant_channels')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_documents', function (Blueprint $table) {
            $table->dropColumn('instant_channels');
        });
    }
};
