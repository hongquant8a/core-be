<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thân nhân — dạng B (1–n không tệp).
 *
 * Ở v1 đây là quan hệ n–n qua bảng nối, cho phép một thân nhân dùng chung cho nhiều người
 * có công. v2 chốt 1–n để đơn giản hoá, chấp nhận đánh đổi: hai hồ sơ cùng khai một người
 * con sẽ có hai dòng độc lập, sửa phải sửa cả hai.
 *
 * Hệ quả trực tiếp: `id_number` KHÔNG unique — cùng một người là thân nhân của hai hồ sơ
 * thì đúng là phải có hai dòng cùng CCCD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->onDelete('cascade');
            $table->string('full_name');
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('id_number', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->foreignId('residential_area_id')->nullable()
                ->constrained('beneficiary_residential_areas')->onDelete('restrict');
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('relationship_id')->nullable()
                ->constrained('beneficiary_relationships')->onDelete('restrict');
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'beneficiary_id']);
            $table->index(['organization_id', 'id_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_dependents');
    }
};
