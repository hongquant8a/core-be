<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tổ dân phố / thôn trở thành trường RIÊNG của người có công (đối xứng với
 * `beneficiary_dependents.residential_area_id`), thay vì chỉ suy ra qua hộ gia đình.
 * Lý do: `household_id` nullable — người có công chưa gán hộ trước đây không có tổ dân phố nào,
 * và không thể lọc/thống kê danh sách theo địa bàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->foreignId('residential_area_id')->nullable()->after('household_id')
                ->constrained('beneficiary_residential_areas')->nullOnDelete();

            $table->index(['organization_id', 'residential_area_id']);
        });

        $this->backfillFromHouseholds();
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'residential_area_id']);
            $table->dropConstrainedForeignId('residential_area_id');
        });
    }

    /**
     * Dữ liệu cũ: tổ dân phố của người có công vốn được suy ra qua hộ — chép sang cột mới để
     * danh sách/thống kê không bị trống sau khi đổi nguồn dữ liệu. Chép theo từng hộ (không dùng
     * `UPDATE ... JOIN`) để chạy được trên cả MySQL lẫn SQLite.
     */
    private function backfillFromHouseholds(): void
    {
        DB::table('beneficiary_households')
            ->select('id', 'residential_area_id')
            ->whereNotNull('residential_area_id')
            ->orderBy('id')
            ->chunk(500, function ($households) {
                foreach ($households as $household) {
                    DB::table('beneficiaries')
                        ->where('household_id', $household->id)
                        ->whereNull('residential_area_id')
                        ->update(['residential_area_id' => $household->residential_area_id]);
                }
            });
    }
};
