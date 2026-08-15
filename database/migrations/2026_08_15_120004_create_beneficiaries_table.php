<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng chính: hồ sơ người có công. Một hồ sơ = một người.
 *
 * KHÔNG có cột `status` — nghiệp vụ không có khái niệm trạng thái cho hồ sơ (hoặc còn trong
 * danh sách quản lý, hoặc đã xoá). Đây là điều CLAUDE.md B3 cho phép sau khi nới: `status`
 * chỉ thêm khi nghiệp vụ thực sự có. Ba bảng danh mục thì ngược lại — có `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->date('birth_date')->nullable();
            // Tách khỏi birth_date để dùng được khi chỉ biết năm sinh. Service tự suy ra
            // birth_year từ birth_date khi có, nên hai cột không bao giờ lệch nhau.
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('id_number', 20)->nullable();
            $table->string('phone', 20)->nullable();
            // restrict: xoá một tổ dân phố đang được dùng phải bị chặn, không cascade.
            $table->foreignId('residential_area_id')->nullable()
                ->constrained('beneficiary_residential_areas')->onDelete('restrict');
            $table->string('address', 500)->nullable();
            // decimal chứ không float: toạ độ dùng để chấm bản đồ, float làm tròn sai
            // ở chữ số thứ 7 là lệch vài chục mét.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // BẮT BUỘC: ba bảng con dùng onDelete('cascade'). Thiếu softDeletes ở đây thì
            // cha xoá cứng khiến MySQL xoá cứng toàn bộ dòng con — bỏ qua SoftDeletes của
            // chúng và để lại file media mồ côi trên đĩa.
            $table->softDeletes();

            // CCCD là định danh duy nhất của một người trong phạm vi tổ chức. Dòng đã xoá
            // mềm vẫn chiếm chỗ trong unique index → store()/import() phải withTrashed()
            // rồi restore(), không create().
            $table->unique(['organization_id', 'id_number']);
            $table->index(['organization_id', 'residential_area_id']);
            $table->index(['organization_id', 'full_name']);
            $table->index(['organization_id', 'birth_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
