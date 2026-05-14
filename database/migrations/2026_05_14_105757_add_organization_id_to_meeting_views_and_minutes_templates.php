<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung organization_id cho 2 model còn thiếu trong module Meeting.
 *
 *  - meeting_views          : log tracking view/download. Derive org từ meeting_id để
 *                              query thống kê nhanh, không cần JOIN.
 *  - meeting_minutes_templates : mỗi tổ chức có template biên bản riêng (logo, layout).
 *
 * Strategy: thêm column nullable trước → backfill từ meetings (cho meeting_views) →
 * giữ nullable (template cũ có thể tồn tại trước khi gán org). Service enforce gán
 * organization_id khi tạo mới sau migration này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_views', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_views', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();
                $table->index(['organization_id', 'viewed_at'], 'meeting_views_org_viewed_idx');
            }
        });

        DB::statement('
            UPDATE meeting_views mv
            INNER JOIN meetings m ON m.id = mv.meeting_id
            SET mv.organization_id = m.organization_id
            WHERE mv.organization_id IS NULL
        ');

        Schema::table('meeting_minutes_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_minutes_templates', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();
                $table->index(['organization_id', 'status'], 'meeting_minutes_tpl_org_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_views', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_views', 'organization_id')) {
                $table->dropIndex('meeting_views_org_viewed_idx');
                $table->dropConstrainedForeignId('organization_id');
            }
        });

        Schema::table('meeting_minutes_templates', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_minutes_templates', 'organization_id')) {
                $table->dropIndex('meeting_minutes_tpl_org_status_idx');
                $table->dropConstrainedForeignId('organization_id');
            }
        });
    }
};
