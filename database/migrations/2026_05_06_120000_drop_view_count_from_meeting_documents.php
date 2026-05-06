<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ view_count khỏi meeting_documents — quyết định không track view per-document.
 * Documents được preload trong meeting.show, FE click "👁 xem" mở file_url trực tiếp
 * (static URL) → BE không có cách track view per-doc cleanly. Giữ download_count
 * vì download có endpoint trung gian /download (count + redirect).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_documents') && Schema::hasColumn('meeting_documents', 'view_count')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->dropColumn('view_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meeting_documents') && ! Schema::hasColumn('meeting_documents', 'view_count')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->unsignedInteger('view_count')->default(0)->after('is_public');
            });
        }
    }
};
