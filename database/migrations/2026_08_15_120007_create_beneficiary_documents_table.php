<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tài liệu hồ sơ — dạng A (1–n có tệp).
 *
 * Mỗi dòng là một tài liệu có tên, kèm nhiều tệp đính kèm qua media collection `files`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->onDelete('cascade');
            $table->string('name');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'beneficiary_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_documents');
    }
};
