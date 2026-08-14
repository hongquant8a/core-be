<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm trường lời dẫn (script) vào bảng meeting_agendas.
 *
 * `script` — lời dẫn/kịch bản của chương trình họp dành riêng cho Chủ trì xem.
 * Lưu dưới dạng HTML (rich text: in đậm, in nghiêng, màu sắc, in hoa...).
 * Null = chưa có lời dẫn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_agendas')) {
            return;
        }
        Schema::table('meeting_agendas', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_agendas', 'script')) {
                $table->mediumText('script')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_agendas')) {
            return;
        }
        Schema::table('meeting_agendas', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_agendas', 'script')) {
                $table->dropColumn('script');
            }
        });
    }
};
