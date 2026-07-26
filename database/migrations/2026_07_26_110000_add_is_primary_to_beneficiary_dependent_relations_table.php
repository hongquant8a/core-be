<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu THÂN NHÂN CHÍNH của từng người có công (tối đa 1 dòng/hồ sơ).
 *
 * Mục đích chính: khi người có công đã mất, hồ sơ vẫn cần một đầu mối liên hệ và một điểm trên
 * bản đồ để cán bộ đến thăm viếng / chi trả — lấy theo thân nhân chính. Cờ nằm trên PIVOT chứ
 * không trên `beneficiary_dependents` vì "chính hay phụ" là tính chất của QUAN HỆ: cùng một người
 * có thể là thân nhân chính của hồ sơ này nhưng chỉ là thân nhân phụ của hồ sơ khác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiary_dependent_relations', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('relationship_type');

            $table->index(['beneficiary_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::table('beneficiary_dependent_relations', function (Blueprint $table) {
            $table->dropIndex(['beneficiary_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }
};
