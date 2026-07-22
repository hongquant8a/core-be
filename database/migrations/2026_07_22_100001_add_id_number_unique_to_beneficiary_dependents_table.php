<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CCCD thân nhân là duy nhất trong mỗi tổ chức (một người chỉ có 1 hồ sơ thân nhân).
     * id_number nullable → MySQL cho phép nhiều NULL (thân nhân chưa có CCCD).
     */
    public function up(): void
    {
        Schema::table('beneficiary_dependents', function (Blueprint $table) {
            $table->unique(['organization_id', 'id_number'], 'beneficiary_dependents_org_id_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiary_dependents', function (Blueprint $table) {
            $table->dropUnique('beneficiary_dependents_org_id_number_unique');
        });
    }
};
