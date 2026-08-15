<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đối tượng của người có công — bảng nối dạng D (n–n có thuộc tính).
 *
 * Nối `beneficiaries` với danh mục `beneficiary_types`, mang cột nghiệp vụ `is_primary`
 * (đối tượng chính) và có tệp đính kèm riêng cho từng dòng. Có khoá chính riêng chứ không
 * phải composite key: spatie cần id để gắn media, và model cần event để `$touches` nổ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_type_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->onDelete('cascade');
            // restrict: xoá một loại đối tượng đang được dùng phải bị chặn, không cascade.
            $table->foreignId('beneficiary_type_id')->constrained('beneficiary_types')->onDelete('restrict');
            // Nhiều nhất một dòng is_primary = true trên mỗi beneficiary_id. Ràng buộc này
            // enforce trong Service chứ không bằng unique index, vì "không có dòng nào là
            // chính" cũng là trạng thái hợp lệ (hồ sơ mới nhập chưa xác định).
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Một người không thuộc cùng một loại đối tượng hai lần. Unique + SoftDeletes
            // → cần nhánh withTrashed()->restore() thay vì create().
            // Đặt tên ngắn tường minh: tên tự sinh dài 68 ký tự, vượt giới hạn 64 của MySQL.
            $table->unique(['beneficiary_id', 'beneficiary_type_id'], 'btr_beneficiary_type_unique');
            $table->index(['organization_id', 'beneficiary_id']);
            $table->index(['beneficiary_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_type_relations');
    }
};
