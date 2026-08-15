<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục Tổ dân phố/Thôn.
 *
 * Tenant-scoped (`organization_id` NOT NULL) — mỗi tổ chức có danh sách riêng, KHÔNG phải
 * danh mục dùng chung dạng E. Không có cột `code`: `name` là định danh duy nhất nên phải
 * UNIQUE theo tổ chức, vì import Excel tra ngược danh mục hoàn toàn dựa vào nó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_residential_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('note')->nullable();
            $table->integer('sort_order')->default(0);
            // active = còn được chọn khi nhập hồ sơ mới; inactive = ngừng dùng nhưng
            // hồ sơ cũ đang tham chiếu vẫn giữ nguyên.
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_residential_areas');
    }
};
