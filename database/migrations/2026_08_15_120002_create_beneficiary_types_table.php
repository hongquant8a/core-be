<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục Loại đối tượng (Thương binh, Bệnh binh, Thân nhân liệt sĩ...).
 *
 * Cùng khuôn với `beneficiary_residential_areas`. Ở v1 đây là enum cứng
 * (`BeneficiaryTypeEnum`) — chuyển thành danh mục DB để thêm loại mới không phải deploy code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('note')->nullable();
            $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('beneficiary_types');
    }
};
