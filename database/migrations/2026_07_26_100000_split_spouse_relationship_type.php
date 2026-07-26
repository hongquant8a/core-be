<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `spouse` (Vợ/Chồng) tách thành `wife`/`husband` — chuyển đổi dữ liệu cũ theo GIỚI TÍNH THÂN NHÂN,
 * vì `relationship_type` mô tả thân nhân là gì của người có công (thân nhân nữ = vợ).
 *
 * `beneficiary_dependents.gender` là NOT NULL nên luôn có giá trị; `male` → chồng, còn lại
 * (`female`, `other`) → vợ. Chọn "vợ" làm mặc định cho `other` vì người có công phần lớn là nam
 * giới nên vợ là trường hợp áp đảo — dòng nào sai cán bộ sửa lại qua CRUD.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('beneficiary_dependent_relations as r')
            ->join('beneficiary_dependents as d', 'd.id', '=', 'r.dependent_id')
            ->where('r.relationship_type', 'spouse')
            ->where('d.gender', 'male')
            ->pluck('r.id');

        if ($ids->isNotEmpty()) {
            DB::table('beneficiary_dependent_relations')
                ->whereIn('id', $ids)
                ->update(['relationship_type' => 'husband']);
        }

        DB::table('beneficiary_dependent_relations')
            ->where('relationship_type', 'spouse')
            ->update(['relationship_type' => 'wife']);
    }

    public function down(): void
    {
        DB::table('beneficiary_dependent_relations')
            ->whereIn('relationship_type', ['wife', 'husband'])
            ->update(['relationship_type' => 'spouse']);
    }
};
