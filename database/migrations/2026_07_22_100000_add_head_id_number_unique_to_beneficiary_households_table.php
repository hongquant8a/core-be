<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CCCD chủ hộ là duy nhất trong mỗi tổ chức (một người chỉ là chủ 1 hộ).
     * head_id_number nullable → MySQL cho phép nhiều NULL (nhiều hộ chưa có CCCD chủ hộ).
     */
    public function up(): void
    {
        Schema::table('beneficiary_households', function (Blueprint $table) {
            $table->unique(['organization_id', 'head_id_number'], 'beneficiary_households_org_head_cccd_unique');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiary_households', function (Blueprint $table) {
            $table->dropUnique('beneficiary_households_org_head_cccd_unique');
        });
    }
};
