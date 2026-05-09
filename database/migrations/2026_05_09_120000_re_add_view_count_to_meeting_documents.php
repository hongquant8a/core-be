<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-add view_count vào meeting_documents — quyết định trước (drop view_count) đảo ngược:
 * thay vì FE mở file_url static, FE giờ đi qua endpoint /download?type=view|download.
 * Endpoint trung gian increment counter tương ứng rồi 302 redirect tới static URL.
 *
 * Cũng thêm cột `kind` vào meeting_views để phân biệt log:
 *  - `meeting`            : lượt xem meeting.show (count.meeting.view middleware)
 *  - `document_view`      : preview tệp đính kèm
 *  - `document_download`  : tải xuống tệp đính kèm
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_documents') && ! Schema::hasColumn('meeting_documents', 'view_count')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->unsignedInteger('view_count')->default(0)->after('download_count');
            });
        }

        if (Schema::hasTable('meeting_views') && ! Schema::hasColumn('meeting_views', 'kind')) {
            Schema::table('meeting_views', function (Blueprint $table) {
                $table->string('kind', 32)->default('meeting')->after('viewed_at')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meeting_documents') && Schema::hasColumn('meeting_documents', 'view_count')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->dropColumn('view_count');
            });
        }

        if (Schema::hasTable('meeting_views') && Schema::hasColumn('meeting_views', 'kind')) {
            Schema::table('meeting_views', function (Blueprint $table) {
                $table->dropIndex(['kind']);
                $table->dropColumn('kind');
            });
        }
    }
};
