<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete cho meetings. Cuộc họp xóa = set deleted_at (giữ data lịch sử thống kê,
 * audit). Sub-resources (agendas, documents, participants, ...) KHÔNG cascade soft delete
 * — chúng vẫn còn trong DB nhưng query qua relation sẽ filter qua meeting đã deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
